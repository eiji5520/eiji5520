"""XBRLパーサー：有価証券報告書から配当関連情報を抽出する。"""

import logging
import re
import zipfile
from dataclasses import dataclass, field
from typing import Any

from lxml import etree

logger = logging.getLogger(__name__)

XBRL_NS = {
    "xbrli": "http://www.xbrl.org/2003/instance",
    "jppfs_cor": "http://disclosure.edinet-fsa.go.jp/taxonomy/jppfs/2023-11-01/jppfs_cor",
    "jpcrp_cor": "http://disclosure.edinet-fsa.go.jp/taxonomy/jpcrp/2023-11-01/jpcrp_cor",
}

# 複数の年度バージョンに対応するためローカル名で検索
DIVIDEND_PER_SHARE_TAGS = [
    "DividendPaidPerShareSummaryOfBusinessResults",
    "DividendPerShareSummaryOfBusinessResults",
    "AnnualDividendPerShareSummaryOfBusinessResults",
    "DividendPaidPerShareAnnualSummaryOfBusinessResults",
]

PAYOUT_RATIO_TAGS = [
    "PayoutRatioSummaryOfBusinessResults",
    "DividendPayoutRatioSummaryOfBusinessResults",
    "PayoutRatioCorrectedSummaryOfBusinessResults",
]

TOTAL_DIVIDEND_TAGS = [
    "TotalDividendPaidAnnualSummaryOfBusinessResults",
    "TotalAmountOfDividendsPaidAnnualSummaryOfBusinessResults",
    "TotalDividendsPaidSummaryOfBusinessResults",
]

CAPITAL_STOCK_TAGS = [
    "CapitalStockSummaryOfBusinessResults",
    "StatedCapitalSummaryOfBusinessResults",
    "CapitalStock",
]

EMPLOYEES_TAGS = [
    "NumberOfEmployees",
    "NumberOfEmployeesSummaryOfBusinessResults",
    "NumberOfEmployeesIFRSSummaryOfBusinessResults",
]

INDUSTRY_TAGS = [
    "IndustryCodeDEI",
    "IndustryCodeWhenConsolidatedFinancialStatementsArePreparedInAccordanceWithIndustrySpecificRegulationsDEI",
]

LISTING_TAGS = [
    "SecurityListingKindDEI",
    "IsSecuritiesListedDEI",
]


@dataclass
class DividendInfo:
    company_name: str = ""
    edinet_code: str = ""
    industry: str = ""
    capital_stock: str = ""
    num_employees: str = ""
    fiscal_period: str = ""
    dividend_per_share: str = ""
    payout_ratio: str = ""
    total_dividend: str = ""
    evidence_url: str = ""
    doc_id: str = ""
    is_listed: bool | None = None
    xbrl_tags_found: dict[str, str] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return {
            "会社名": self.company_name,
            "EDINETコード": self.edinet_code,
            "業種": self.industry,
            "資本金": self.capital_stock,
            "従業員数": self.num_employees,
            "決算期": self.fiscal_period,
            "1株配当": self.dividend_per_share,
            "配当性向": self.payout_ratio,
            "配当金総額": self.total_dividend,
            "エビデンスURL": self.evidence_url,
            "docID": self.doc_id,
        }


def _find_xbrl_files(zf: zipfile.ZipFile) -> list[str]:
    """ZIPから解析対象のXBRLファイルパスを返す。"""
    candidates = []
    for name in zf.namelist():
        lower = name.lower()
        if lower.endswith(".xbrl") or lower.endswith(".xml"):
            if "audit" not in lower and "manifest" not in lower and "catalog" not in lower:
                candidates.append(name)
    # 本文XBRLを優先（publicDoc内のもの）
    public = [c for c in candidates if "publicdoc" in c.lower() or "PublicDoc" in c]
    return public if public else candidates


def _find_value_by_local_names(
    root: etree._Element, tag_candidates: list[str], context_filter: str | None = None
) -> tuple[str, str]:
    """ローカル名のリストからマッチする最初の値を返す。(値, タグ名)"""
    for elem in root.iter():
        local = etree.QName(elem.tag).localname if isinstance(elem.tag, str) else ""
        if local in tag_candidates:
            if context_filter:
                ctx = elem.get("contextRef", "")
                if context_filter not in ctx:
                    continue
            text = (elem.text or "").strip()
            if text:
                return text, local
    return "", ""


def _find_all_values_by_local_names(
    root: etree._Element, tag_candidates: list[str]
) -> list[tuple[str, str, str]]:
    """ローカル名リストにマッチする全要素を返す。(値, タグ名, contextRef)"""
    results = []
    for elem in root.iter():
        local = etree.QName(elem.tag).localname if isinstance(elem.tag, str) else ""
        if local in tag_candidates:
            text = (elem.text or "").strip()
            ctx = elem.get("contextRef", "")
            if text:
                results.append((text, local, ctx))
    return results


def _detect_listing_status(root: etree._Element) -> bool | None:
    """上場区分を判定する。True=上場, False=非上場, None=判定不能"""
    for elem in root.iter():
        local = etree.QName(elem.tag).localname if isinstance(elem.tag, str) else ""
        if local in LISTING_TAGS:
            text = (elem.text or "").strip().lower()
            if text in ("上場", "listed", "true"):
                return True
            if text in ("非上場", "unlisted", "false"):
                return False
            if "非上場" in text or "unlisted" in text:
                return False
            if "上場" in text or "listed" in text:
                return True
    return None


def _extract_fiscal_period(root: etree._Element) -> str:
    """決算期を取得する。"""
    period_tags = [
        "CurrentFiscalYearEndDateDEI",
        "CurrentPeriodEndDateDEI",
        "AccountingPeriodCoverPageTextBlock",
    ]
    val, _ = _find_value_by_local_names(root, period_tags)
    if val:
        return val

    # contextRefから期間を推定
    for elem in root.iter():
        ctx = elem.get("contextRef", "")
        if "CurrentYear" in ctx or "CurrentYearDuration" in ctx:
            # instant要素からendDateを取得
            pass
    return ""


def _get_current_year_context_keywords() -> list[str]:
    return ["CurrentYear", "CurrentYearInstant", "CurrentYearDuration",
            "Prior1Year", "FilingDateInstant"]


def parse_xbrl_zip(
    zf: zipfile.ZipFile,
    doc_id: str,
    submitter_name: str = "",
    submitter_edinet_code: str = "",
    evidence_url: str = "",
) -> DividendInfo | None:
    """ZIPからXBRLを解析し、配当情報を抽出する。"""
    info = DividendInfo(
        company_name=submitter_name,
        edinet_code=submitter_edinet_code,
        doc_id=doc_id,
        evidence_url=evidence_url,
    )

    xbrl_files = _find_xbrl_files(zf)
    if not xbrl_files:
        logger.warning("docID=%s: XBRLファイルが見つかりません", doc_id)
        return None

    parsed_any = False
    for xbrl_path in xbrl_files:
        try:
            with zf.open(xbrl_path) as f:
                tree = etree.parse(f)
                root = tree.getroot()
        except Exception as e:
            logger.warning("docID=%s: %s のパースに失敗: %s", doc_id, xbrl_path, e)
            continue

        parsed_any = True

        # 上場区分判定
        if info.is_listed is None:
            info.is_listed = _detect_listing_status(root)

        # 会社名（DEIから補完）
        if not info.company_name:
            val, tag = _find_value_by_local_names(
                root, ["FilerNameInJapaneseDEI", "CompanyNameCoverPage"]
            )
            if val:
                info.company_name = val

        # 業種
        if not info.industry:
            val, tag = _find_value_by_local_names(root, INDUSTRY_TAGS)
            if val:
                info.industry = val
                info.xbrl_tags_found["industry"] = tag

        # 資本金
        if not info.capital_stock:
            val, tag = _find_value_by_local_names(root, CAPITAL_STOCK_TAGS)
            if val:
                info.capital_stock = val
                info.xbrl_tags_found["capital_stock"] = tag

        # 従業員数
        if not info.num_employees:
            val, tag = _find_value_by_local_names(root, EMPLOYEES_TAGS)
            if val:
                info.num_employees = val
                info.xbrl_tags_found["num_employees"] = tag

        # 決算期
        if not info.fiscal_period:
            info.fiscal_period = _extract_fiscal_period(root)

        # 1株当たり配当額
        if not info.dividend_per_share:
            # 当期分を優先で探す
            all_divs = _find_all_values_by_local_names(root, DIVIDEND_PER_SHARE_TAGS)
            current_year_divs = [
                (v, t, c) for v, t, c in all_divs
                if any(kw in c for kw in _get_current_year_context_keywords())
            ]
            if current_year_divs:
                info.dividend_per_share = current_year_divs[0][0]
                info.xbrl_tags_found["dividend_per_share"] = current_year_divs[0][1]
            elif all_divs:
                info.dividend_per_share = all_divs[0][0]
                info.xbrl_tags_found["dividend_per_share"] = all_divs[0][1]

        # 配当性向
        if not info.payout_ratio:
            val, tag = _find_value_by_local_names(root, PAYOUT_RATIO_TAGS)
            if val:
                info.payout_ratio = val
                info.xbrl_tags_found["payout_ratio"] = tag

        # 配当金総額
        if not info.total_dividend:
            val, tag = _find_value_by_local_names(root, TOTAL_DIVIDEND_TAGS)
            if val:
                info.total_dividend = val
                info.xbrl_tags_found["total_dividend"] = tag

    if not parsed_any:
        logger.warning("docID=%s: パース可能なXBRLファイルがありません", doc_id)
        return None

    # 取得できなかった項目のログ
    missing = []
    if not info.dividend_per_share:
        missing.append("1株配当")
    if not info.payout_ratio:
        missing.append("配当性向")
    if not info.total_dividend:
        missing.append("配当金総額")
    if not info.capital_stock:
        missing.append("資本金")
    if not info.num_employees:
        missing.append("従業員数")
    if not info.fiscal_period:
        missing.append("決算期")
    if not info.industry:
        missing.append("業種")

    if missing:
        logger.info(
            "docID=%s (%s): 未取得項目: %s",
            doc_id, info.company_name, ", ".join(missing),
        )

    return info
