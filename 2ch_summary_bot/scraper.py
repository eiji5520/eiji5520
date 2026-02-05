"""
5ちゃんねるスレッド解析モジュール

このモジュールは5ちゃんねる（5ch.net）のスレッドからレスを取得し、
解析・整形する機能を提供します。
"""

import re
import time
from dataclasses import dataclass
from typing import Optional
from urllib.parse import urlparse

import requests
from bs4 import BeautifulSoup


@dataclass
class Response:
    """
    5chの1レスを表すデータクラス

    Attributes:
        number: レス番号
        name: 投稿者名
        date: 投稿日時
        user_id: 投稿者ID
        body: 本文
        anchor_count: このレスへのアンカー数（人気度の指標）
    """
    number: int
    name: str
    date: str
    user_id: str
    body: str
    anchor_count: int = 0


class ThreadScraper:
    """
    5ちゃんねるスレッド解析クラス

    指定されたスレッドURLからレスを取得し、解析します。
    """

    # 5ch用のデフォルトUser-Agent
    DEFAULT_USER_AGENT = (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/120.0.0.0 Safari/537.36"
    )

    def __init__(self, user_agent: Optional[str] = None, request_delay: float = 1.0):
        """
        初期化

        Args:
            user_agent: HTTPリクエストに使用するUser-Agent
            request_delay: リクエスト間隔（秒）
        """
        self.user_agent = user_agent or self.DEFAULT_USER_AGENT
        self.request_delay = request_delay
        self.session = requests.Session()
        self.session.headers.update({
            "User-Agent": self.user_agent,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "ja,en-US;q=0.7,en;q=0.3",
            "Accept-Encoding": "gzip, deflate",
            "Connection": "keep-alive",
        })

    def fetch_thread(self, url: str) -> str:
        """
        スレッドのHTMLを取得

        Args:
            url: スレッドURL

        Returns:
            取得したHTML文字列

        Raises:
            requests.RequestException: HTTP通信エラー
            ValueError: 無効なURL
        """
        # URLの検証
        parsed = urlparse(url)
        if not parsed.scheme or not parsed.netloc:
            raise ValueError(f"無効なURL形式です: {url}")

        # 5ch系ドメインの確認
        valid_domains = ["5ch.net", "2ch.sc", "bbspink.com"]
        if not any(domain in parsed.netloc for domain in valid_domains):
            raise ValueError(f"5ch系以外のURLは対応していません: {url}")

        try:
            # リクエスト実行
            response = self.session.get(url, timeout=30)
            response.raise_for_status()

            # エンコーディングの自動検出（5chは通常Shift_JIS）
            response.encoding = response.apparent_encoding or "shift_jis"

            return response.text

        except requests.Timeout:
            raise requests.RequestException(f"タイムアウト: {url}")
        except requests.HTTPError as e:
            raise requests.RequestException(f"HTTPエラー ({e.response.status_code}): {url}")

    def parse_thread(self, html: str) -> list[Response]:
        """
        HTMLからレスを解析

        Args:
            html: スレッドのHTML

        Returns:
            Responseオブジェクトのリスト
        """
        soup = BeautifulSoup(html, "lxml")
        responses = []

        # 5chの新形式（div.post）を解析
        posts = soup.select("div.post")

        if posts:
            # 新形式の解析
            responses = self._parse_new_format(posts)
        else:
            # 旧形式（dl/dt/dd）の解析を試行
            responses = self._parse_old_format(soup)

        # アンカーカウントを計算
        self._count_anchors(responses)

        return responses

    def _parse_new_format(self, posts) -> list[Response]:
        """
        新形式（div.post）のレスを解析

        Args:
            posts: BeautifulSoupのResultSet

        Returns:
            Responseオブジェクトのリスト
        """
        responses = []

        for post in posts:
            try:
                # レス番号
                number_elem = post.select_one(".number")
                number = int(number_elem.get_text(strip=True)) if number_elem else 0

                # 名前
                name_elem = post.select_one(".name")
                name = name_elem.get_text(strip=True) if name_elem else "名無し"

                # 日付
                date_elem = post.select_one(".date")
                date = date_elem.get_text(strip=True) if date_elem else ""

                # ID
                uid_elem = post.select_one(".uid")
                user_id = uid_elem.get_text(strip=True) if uid_elem else ""
                # "ID:"プレフィックスを除去
                user_id = re.sub(r"^ID:", "", user_id)

                # 本文
                message_elem = post.select_one(".message")
                if message_elem:
                    # <br>を改行に変換
                    for br in message_elem.find_all("br"):
                        br.replace_with("\n")
                    body = message_elem.get_text(strip=True)
                else:
                    body = ""

                responses.append(Response(
                    number=number,
                    name=name,
                    date=date,
                    user_id=user_id,
                    body=body
                ))

            except (AttributeError, ValueError) as e:
                # 解析エラーはスキップして続行
                continue

        return responses

    def _parse_old_format(self, soup: BeautifulSoup) -> list[Response]:
        """
        旧形式（dl/dt/dd）のレスを解析

        Args:
            soup: BeautifulSoupオブジェクト

        Returns:
            Responseオブジェクトのリスト
        """
        responses = []

        # dt要素（ヘッダー）とdd要素（本文）のペアを取得
        dts = soup.select("dl.thread dt")
        dds = soup.select("dl.thread dd")

        for dt, dd in zip(dts, dds):
            try:
                # dtのテキストからレス情報を抽出
                dt_text = dt.get_text(strip=True)

                # レス番号を抽出（先頭の数字）
                number_match = re.match(r"(\d+)", dt_text)
                number = int(number_match.group(1)) if number_match else 0

                # 名前を抽出
                name_elem = dt.select_one(".name")
                name = name_elem.get_text(strip=True) if name_elem else "名無し"

                # 日付を抽出
                date_match = re.search(r"(\d{4}/\d{2}/\d{2}[^\s]*\s*\d{2}:\d{2}:\d{2})", dt_text)
                date = date_match.group(1) if date_match else ""

                # IDを抽出
                id_match = re.search(r"ID:([a-zA-Z0-9+/]+)", dt_text)
                user_id = id_match.group(1) if id_match else ""

                # 本文を抽出
                for br in dd.find_all("br"):
                    br.replace_with("\n")
                body = dd.get_text(strip=True)

                responses.append(Response(
                    number=number,
                    name=name,
                    date=date,
                    user_id=user_id,
                    body=body
                ))

            except (AttributeError, ValueError):
                continue

        return responses

    def _count_anchors(self, responses: list[Response]) -> None:
        """
        各レスへのアンカー数をカウント

        他のレスからどれだけ参照（アンカー）されているかを計算します。

        Args:
            responses: Responseオブジェクトのリスト（更新される）
        """
        # アンカーパターン: >>数字 または >数字
        anchor_pattern = re.compile(r">>?(\d+)")

        # 各レスの本文からアンカーを抽出
        for res in responses:
            anchors = anchor_pattern.findall(res.body)
            for anchor_num in anchors:
                try:
                    anchor_int = int(anchor_num)
                    # 該当するレスのアンカーカウントを増加
                    for target in responses:
                        if target.number == anchor_int:
                            target.anchor_count += 1
                            break
                except ValueError:
                    continue

    def filter_responses(
        self,
        responses: list[Response],
        include_first_n: int = 10,
        min_anchor_count: int = 2
    ) -> list[Response]:
        """
        レスをフィルタリング

        最初のN件と、アンカーが一定数以上付いている人気レスを抽出します。

        Args:
            responses: 全レスのリスト
            include_first_n: 最初から含めるレス数（デフォルト: 10）
            min_anchor_count: 人気とみなす最小アンカー数（デフォルト: 2）

        Returns:
            フィルタリングされたResponseリスト
        """
        filtered = []
        seen_numbers = set()

        # 最初のN件を追加
        for res in responses:
            if res.number <= include_first_n:
                if res.number not in seen_numbers:
                    filtered.append(res)
                    seen_numbers.add(res.number)

        # 人気レス（アンカーが多いもの）を追加
        for res in responses:
            if res.anchor_count >= min_anchor_count:
                if res.number not in seen_numbers:
                    filtered.append(res)
                    seen_numbers.add(res.number)

        # レス番号順にソート
        filtered.sort(key=lambda x: x.number)

        return filtered

    def format_to_html(self, responses: list[Response], thread_title: str = "") -> str:
        """
        レスをまとめサイト風HTMLに整形

        Args:
            responses: Responseオブジェクトのリスト
            thread_title: スレッドタイトル（オプション）

        Returns:
            整形されたHTML文字列
        """
        html_parts = []

        # スレッドタイトルがあれば追加
        if thread_title:
            html_parts.append(f"<h2>{self._escape_html(thread_title)}</h2>")

        html_parts.append('<dl class="thread-summary">')

        for res in responses:
            # 名前の装飾（>>1 は強調）
            name_class = "name op" if res.number == 1 else "name"

            # 人気レスにはマーク
            popular_mark = " 🔥" if res.anchor_count >= 3 else ""

            # レス番号と名前
            html_parts.append(
                f'<dt class="res-header">'
                f'<span class="res-number">{res.number}</span>：'
                f'<span class="{name_class}">{self._escape_html(res.name)}</span> '
                f'<span class="date">{self._escape_html(res.date)}</span> '
                f'<span class="uid">ID:{self._escape_html(res.user_id)}</span>'
                f'{popular_mark}'
                f'</dt>'
            )

            # 本文（改行を<br>に変換、アンカーをリンク化）
            body_html = self._format_body(res.body)
            html_parts.append(f'<dd class="res-body">{body_html}</dd>')

        html_parts.append('</dl>')

        return "\n".join(html_parts)

    def _format_body(self, body: str) -> str:
        """
        本文を整形

        - 改行を<br>に変換
        - アンカー（>>数字）を強調
        - HTMLエスケープ

        Args:
            body: 本文テキスト

        Returns:
            整形されたHTML
        """
        # HTMLエスケープ
        escaped = self._escape_html(body)

        # 改行を<br>に変換
        escaped = escaped.replace("\n", "<br>\n")

        # アンカーを強調表示
        escaped = re.sub(
            r"(&gt;&gt;)(\d+)",
            r'<span class="anchor">\1\2</span>',
            escaped
        )

        return escaped

    def _escape_html(self, text: str) -> str:
        """
        HTMLエスケープ

        Args:
            text: エスケープする文字列

        Returns:
            エスケープされた文字列
        """
        return (
            text
            .replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
            .replace('"', "&quot;")
            .replace("'", "&#39;")
        )

    def get_thread_title(self, html: str) -> str:
        """
        HTMLからスレッドタイトルを抽出

        Args:
            html: スレッドのHTML

        Returns:
            スレッドタイトル
        """
        soup = BeautifulSoup(html, "lxml")

        # titleタグから取得
        title_elem = soup.select_one("title")
        if title_elem:
            title = title_elem.get_text(strip=True)
            # 末尾の不要な部分を削除（例: " | 5ちゃんねる"）
            title = re.sub(r"\s*[\|｜]\s*5ch.*$", "", title)
            return title

        # h1タグから取得を試行
        h1_elem = soup.select_one("h1")
        if h1_elem:
            return h1_elem.get_text(strip=True)

        return "無題"

    def scrape(self, url: str, include_first_n: int = 10, min_anchor_count: int = 2) -> tuple[str, str]:
        """
        スレッドをスクレイピングしてHTML形式で返す（一括処理）

        Args:
            url: スレッドURL
            include_first_n: 最初から含めるレス数
            min_anchor_count: 人気とみなす最小アンカー数

        Returns:
            (スレッドタイトル, 整形されたHTML) のタプル
        """
        # HTML取得
        html = self.fetch_thread(url)

        # タイトル抽出
        title = self.get_thread_title(html)

        # レス解析
        responses = self.parse_thread(html)

        if not responses:
            raise ValueError("レスを取得できませんでした。スレッドが存在しないか、形式が対応していません。")

        # フィルタリング
        filtered = self.filter_responses(
            responses,
            include_first_n=include_first_n,
            min_anchor_count=min_anchor_count
        )

        # HTML整形
        formatted_html = self.format_to_html(filtered, title)

        return title, formatted_html


# 単体テスト用
if __name__ == "__main__":
    # テスト実行
    scraper = ThreadScraper()

    # テスト用のサンプルHTML
    sample_html = """
    <html>
    <head><title>テストスレッド | 5ch</title></head>
    <body>
    <div class="post">
        <span class="number">1</span>
        <span class="name">名無しさん</span>
        <span class="date">2024/01/01 12:00:00</span>
        <span class="uid">ID:abc123</span>
        <div class="message">これはテストです</div>
    </div>
    <div class="post">
        <span class="number">2</span>
        <span class="name">名無しさん</span>
        <span class="date">2024/01/01 12:01:00</span>
        <span class="uid">ID:def456</span>
        <div class="message">>>1<br>同意です</div>
    </div>
    </body>
    </html>
    """

    # 解析テスト
    responses = scraper.parse_thread(sample_html)
    print(f"取得したレス数: {len(responses)}")
    for res in responses:
        print(f"  #{res.number}: {res.name} - {res.body[:20]}...")
