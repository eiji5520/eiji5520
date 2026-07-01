"""EDINET API v2 クライアント"""

import io
import logging
import os
import time
import zipfile
from typing import Any

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

logger = logging.getLogger(__name__)

BASE_URL = "https://api.edinet-fsa.go.jp/api/v2"
REQUEST_INTERVAL = 1.0
TIMEOUT = 60


def _get_api_key() -> str:
    key = os.environ.get("EDINET_API_KEY")
    if not key:
        raise EnvironmentError(
            "環境変数 EDINET_API_KEY が設定されていません。"
            "EDINET API v2のサブスクリプションキーを設定してください。"
        )
    return key


def _build_session() -> requests.Session:
    session = requests.Session()
    retry = Retry(
        total=3,
        backoff_factor=1,
        status_forcelist=[429, 500, 502, 503, 504],
        allowed_methods=["GET"],
    )
    adapter = HTTPAdapter(max_retries=retry)
    session.mount("https://", adapter)
    session.mount("http://", adapter)
    return session


_session = _build_session()
_last_request_time = 0.0


def _throttle() -> None:
    global _last_request_time
    elapsed = time.time() - _last_request_time
    if elapsed < REQUEST_INTERVAL:
        time.sleep(REQUEST_INTERVAL - elapsed)
    _last_request_time = time.time()


def get_documents(date: str, doc_type_code: str = "120") -> list[dict[str, Any]]:
    """指定日の書類一覧を取得し、指定docTypeCodeでフィルタして返す。

    Args:
        date: YYYY-MM-DD形式の日付
        doc_type_code: 書類種別コード（デフォルト: 120=有価証券報告書）
    """
    _throttle()
    params = {
        "date": date,
        "type": 2,
        "Subscription-Key": _get_api_key(),
    }
    logger.info("書類一覧取得: date=%s", date)
    resp = _session.get(f"{BASE_URL}/documents.json", params=params, timeout=TIMEOUT)
    resp.raise_for_status()
    data = resp.json()

    results = data.get("results", [])
    filtered = [
        doc for doc in results
        if doc.get("docTypeCode") == doc_type_code
    ]
    logger.info(
        "date=%s: 全%d件中 docTypeCode=%s は%d件",
        date, len(results), doc_type_code, len(filtered),
    )
    return filtered


def download_xbrl_zip(doc_id: str) -> zipfile.ZipFile | None:
    """書類のXBRL(type=5)をダウンロードしZipFileオブジェクトとして返す。"""
    _throttle()
    params = {
        "type": 5,
        "Subscription-Key": _get_api_key(),
    }
    logger.info("XBRLダウンロード: docID=%s", doc_id)
    resp = _session.get(
        f"{BASE_URL}/documents/{doc_id}", params=params, timeout=TIMEOUT
    )
    if resp.status_code == 404:
        logger.warning("docID=%s: XBRL未提供(404)", doc_id)
        return None
    resp.raise_for_status()

    content_type = resp.headers.get("Content-Type", "")
    if "zip" not in content_type and "octet-stream" not in content_type:
        logger.warning(
            "docID=%s: 想定外のContent-Type=%s", doc_id, content_type
        )
        return None

    try:
        return zipfile.ZipFile(io.BytesIO(resp.content))
    except zipfile.BadZipFile:
        logger.error("docID=%s: ZIPファイル破損", doc_id)
        return None


def build_disclosure_url(doc_id: str) -> str:
    """EDINET書類閲覧URLを構築する。"""
    return (
        f"https://disclosure2.edinet-fsa.go.jp/WZEK0040.aspx?S100{doc_id}"
        if not doc_id.startswith("S")
        else f"https://disclosure2.edinet-fsa.go.jp/WZEK0040.aspx?{doc_id}"
    )
