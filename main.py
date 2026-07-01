"""EDINET非上場企業 配当情報収集ツール

有価証券報告書の提出義務がある非上場企業の配当情報を
EDINET API v2から自動収集し、CSV/Excelで出力する。
"""

import argparse
import logging
import sys
import time
from datetime import datetime, timedelta

import pandas as pd

from edinet_client import build_disclosure_url, download_xbrl_zip, get_documents
from xbrl_parser import parse_xbrl_zip

logger = logging.getLogger(__name__)


def _setup_logging(verbose: bool = False) -> None:
    level = logging.DEBUG if verbose else logging.INFO
    logging.basicConfig(
        level=level,
        format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )


def _date_range(start: str, end: str) -> list[str]:
    """start〜end（YYYY-MM-DD）の日付リストを返す。"""
    s = datetime.strptime(start, "%Y-%m-%d")
    e = datetime.strptime(end, "%Y-%m-%d")
    dates = []
    current = s
    while current <= e:
        dates.append(current.strftime("%Y-%m-%d"))
        current += timedelta(days=1)
    return dates


def _is_unlisted_from_doc(doc: dict) -> bool | None:
    """書類一覧APIのレスポンスから上場区分を判定する。
    listingなし or "0"=非上場, "1"=上場。
    """
    listing = doc.get("listingFlag")
    if listing is None:
        return None
    return listing == "0"


def collect_dividends(
    start_date: str,
    end_date: str,
    skip_listed: bool = True,
) -> pd.DataFrame:
    """指定期間の非上場企業の配当情報を収集する。"""
    dates = _date_range(start_date, end_date)
    logger.info(
        "収集期間: %s 〜 %s (%d日間)", start_date, end_date, len(dates)
    )

    all_docs: list[dict] = []
    for date in dates:
        try:
            docs = get_documents(date, doc_type_code="120")
            all_docs.extend([(date, doc) for doc in docs])
        except Exception as e:
            logger.error("date=%s: 書類一覧取得に失敗: %s", date, e)

    logger.info("有価証券報告書 合計: %d件", len(all_docs))

    # 上場企業をフィルタ
    unlisted_docs = []
    listed_count = 0
    unknown_count = 0

    for date, doc in all_docs:
        is_unlisted = _is_unlisted_from_doc(doc)
        if is_unlisted is False and skip_listed:
            listed_count += 1
            continue
        if is_unlisted is None:
            unknown_count += 1
        unlisted_docs.append((date, doc))

    logger.info(
        "上場除外: %d件, 判定不能: %d件, 対象: %d件",
        listed_count, unknown_count, len(unlisted_docs),
    )

    # XBRL取得・パース
    results = []
    success_count = 0
    fail_count = 0
    start_time = time.time()

    for i, (date, doc) in enumerate(unlisted_docs, 1):
        doc_id = doc.get("docID", "")
        submitter = doc.get("filerName", "")
        edinet_code = doc.get("edinetCode", "")
        evidence_url = build_disclosure_url(doc_id)

        logger.info(
            "[%d/%d] %s (%s) docID=%s",
            i, len(unlisted_docs), submitter, edinet_code, doc_id,
        )

        try:
            zf = download_xbrl_zip(doc_id)
            if zf is None:
                logger.warning("docID=%s: XBRLダウンロード失敗", doc_id)
                fail_count += 1
                continue

            with zf:
                info = parse_xbrl_zip(
                    zf,
                    doc_id=doc_id,
                    submitter_name=submitter,
                    submitter_edinet_code=edinet_code,
                    evidence_url=evidence_url,
                )

            if info is None:
                fail_count += 1
                continue

            # XBRL内の上場区分で再判定
            if skip_listed and info.is_listed is True:
                logger.info(
                    "docID=%s (%s): XBRL上場区分=上場のため除外", doc_id, submitter
                )
                listed_count += 1
                continue

            results.append(info.to_dict())
            success_count += 1

        except Exception as e:
            logger.error("docID=%s (%s): 解析エラー: %s", doc_id, submitter, e)
            fail_count += 1

    elapsed = time.time() - start_time

    logger.info("=" * 60)
    logger.info("実行サマリー")
    logger.info("  期間: %s 〜 %s", start_date, end_date)
    logger.info("  有報取得数: %d件", len(all_docs))
    logger.info("  上場除外数: %d件", listed_count)
    logger.info("  解析成功: %d件", success_count)
    logger.info("  解析失敗: %d件", fail_count)
    logger.info("  実行時間: %.1f秒", elapsed)
    logger.info("=" * 60)

    if not results:
        logger.warning("該当データがありません。")
        return pd.DataFrame()

    df = pd.DataFrame(results)
    column_order = [
        "会社名", "EDINETコード", "業種", "資本金", "従業員数",
        "決算期", "1株配当", "配当性向", "配当金総額",
        "エビデンスURL", "docID",
    ]
    for col in column_order:
        if col not in df.columns:
            df[col] = ""
    return df[column_order]


def main() -> None:
    parser = argparse.ArgumentParser(
        description="EDINET非上場企業 配当情報収集ツール"
    )
    parser.add_argument(
        "--start-date",
        default=(datetime.now() - timedelta(days=365)).strftime("%Y-%m-%d"),
        help="収集開始日 (YYYY-MM-DD, デフォルト: 1年前)",
    )
    parser.add_argument(
        "--end-date",
        default=datetime.now().strftime("%Y-%m-%d"),
        help="収集終了日 (YYYY-MM-DD, デフォルト: 今日)",
    )
    parser.add_argument(
        "--output-csv",
        default="dividend_unlisted.csv",
        help="CSV出力ファイル名",
    )
    parser.add_argument(
        "--output-excel",
        default="dividend_unlisted.xlsx",
        help="Excel出力ファイル名",
    )
    parser.add_argument(
        "--include-listed",
        action="store_true",
        help="上場企業も含める（デフォルトは非上場のみ）",
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_true",
        help="デバッグログを出力する",
    )
    args = parser.parse_args()

    _setup_logging(args.verbose)

    logger.info("EDINET非上場企業 配当情報収集ツール 開始")

    df = collect_dividends(
        start_date=args.start_date,
        end_date=args.end_date,
        skip_listed=not args.include_listed,
    )

    if df.empty:
        logger.info("出力対象なし。終了します。")
        sys.exit(0)

    df.to_csv(args.output_csv, index=False, encoding="utf-8-sig")
    logger.info("CSV出力: %s (%d行)", args.output_csv, len(df))

    df.to_excel(args.output_excel, index=False, engine="openpyxl")
    logger.info("Excel出力: %s (%d行)", args.output_excel, len(df))

    logger.info("完了")


if __name__ == "__main__":
    main()
