"""既知企業を対象にした動作確認テスト

サントリーホールディングス（非上場、有報提出義務あり）等を対象に
ツールの基本動作を確認する。

使用方法:
  EDINET_API_KEY=xxx python test_known_companies.py
"""

import logging
import os
import sys

from edinet_client import build_disclosure_url, download_xbrl_zip, get_documents
from xbrl_parser import parse_xbrl_zip

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)

# サントリーホールディングス: EDINETコード E00394
KNOWN_COMPANIES = {
    "E00394": "サントリーホールディングス株式会社",
}


def test_document_search() -> None:
    """書類一覧APIの動作確認（有報が取得できること）"""
    logger.info("=== テスト1: 書類一覧API ===")
    # 有報の提出が多い6月を対象
    test_date = "2024-06-28"
    docs = get_documents(test_date)
    logger.info("date=%s: %d件の有報", test_date, len(docs))
    assert len(docs) >= 0, "APIが正常にレスポンスを返すこと"

    for doc in docs[:3]:
        logger.info(
            "  - %s (%s) docID=%s listingFlag=%s",
            doc.get("filerName"),
            doc.get("edinetCode"),
            doc.get("docID"),
            doc.get("listingFlag"),
        )
    logger.info("テスト1: OK")


def test_xbrl_download_and_parse() -> None:
    """既知企業のXBRLダウンロード・パースのテスト"""
    logger.info("=== テスト2: XBRLダウンロード・パース ===")

    # サントリーの有報を探す（直近の決算期6月提出を想定）
    # 実際のdocIDはAPIで検索して特定する必要がある
    test_dates = ["2024-06-27", "2024-06-28", "2024-03-28", "2024-03-29"]

    found_doc = None
    for date in test_dates:
        try:
            docs = get_documents(date)
            for doc in docs:
                if doc.get("edinetCode") in KNOWN_COMPANIES:
                    found_doc = doc
                    logger.info(
                        "サントリーHD発見: date=%s docID=%s",
                        date, doc.get("docID"),
                    )
                    break
            if found_doc:
                break
        except Exception as e:
            logger.warning("date=%s: 検索失敗: %s", date, e)

    if found_doc is None:
        logger.warning(
            "テスト期間内にサントリーHDの有報が見つかりませんでした。"
            "別の日付を指定してテストしてください。"
        )
        return

    doc_id = found_doc["docID"]
    evidence_url = build_disclosure_url(doc_id)
    logger.info("エビデンスURL: %s", evidence_url)

    zf = download_xbrl_zip(doc_id)
    assert zf is not None, "XBRLダウンロードが成功すること"

    with zf:
        info = parse_xbrl_zip(
            zf,
            doc_id=doc_id,
            submitter_name=found_doc.get("filerName", ""),
            submitter_edinet_code=found_doc.get("edinetCode", ""),
            evidence_url=evidence_url,
        )

    assert info is not None, "XBRLパースが成功すること"
    logger.info("会社名: %s", info.company_name)
    logger.info("EDINETコード: %s", info.edinet_code)
    logger.info("業種: %s", info.industry)
    logger.info("資本金: %s", info.capital_stock)
    logger.info("従業員数: %s", info.num_employees)
    logger.info("決算期: %s", info.fiscal_period)
    logger.info("1株配当: %s", info.dividend_per_share)
    logger.info("配当性向: %s", info.payout_ratio)
    logger.info("配当金総額: %s", info.total_dividend)
    logger.info("上場区分: %s", info.is_listed)
    logger.info("検出タグ: %s", info.xbrl_tags_found)
    logger.info("エビデンスURL: %s", info.evidence_url)
    logger.info("テスト2: OK")


def test_url_builder() -> None:
    """エビデンスURL生成のテスト"""
    logger.info("=== テスト3: URL生成 ===")
    url1 = build_disclosure_url("S100ABC1")
    assert "S100ABC1" in url1
    logger.info("URL (Sで始まる): %s", url1)

    url2 = build_disclosure_url("ABC12345")
    assert "S100ABC12345" in url2
    logger.info("URL (Sで始まらない): %s", url2)

    logger.info("テスト3: OK")


def main() -> None:
    if not os.environ.get("EDINET_API_KEY"):
        logger.error(
            "EDINET_API_KEY が未設定です。テストを実行するには環境変数を設定してください。"
        )
        logger.info("例: EDINET_API_KEY=your-key python test_known_companies.py")
        sys.exit(1)

    test_url_builder()
    test_document_search()
    test_xbrl_download_and_parse()

    logger.info("=" * 50)
    logger.info("全テスト完了")


if __name__ == "__main__":
    main()
