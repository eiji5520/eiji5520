"""
ライブドアブログ投稿モジュール

このモジュールはLivedoor BlogのAtomPub APIを使用して
記事を投稿する機能を提供します。
"""

import base64
import hashlib
import secrets
from datetime import datetime, timezone
from typing import Optional
from xml.etree import ElementTree as ET

import requests


class WSSEAuth:
    """
    WSSE認証ヘッダー生成クラス

    Livedoor BlogのAtomPub APIで必要なWSSE認証を生成します。
    """

    def __init__(self, username: str, api_key: str):
        """
        初期化

        Args:
            username: ライブドアブログのユーザーID
            api_key: AtomPub APIキー
        """
        self.username = username
        self.api_key = api_key

    def generate_header(self) -> dict[str, str]:
        """
        WSSE認証ヘッダーを生成

        Returns:
            HTTPヘッダー用の辞書
        """
        # ノンス（ランダム値）を生成
        nonce = secrets.token_bytes(20)
        nonce_b64 = base64.b64encode(nonce).decode("utf-8")

        # タイムスタンプ（ISO 8601形式）
        created = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

        # パスワードダイジェストを計算
        # PasswordDigest = Base64(SHA1(Nonce + Created + Password))
        digest_input = nonce + created.encode("utf-8") + self.api_key.encode("utf-8")
        password_digest = base64.b64encode(
            hashlib.sha1(digest_input).digest()
        ).decode("utf-8")

        # WSSEヘッダーを構築
        wsse_header = (
            f'UsernameToken Username="{self.username}", '
            f'PasswordDigest="{password_digest}", '
            f'Nonce="{nonce_b64}", '
            f'Created="{created}"'
        )

        return {
            "X-WSSE": wsse_header,
            "Authorization": "WSSE profile=\"UsernameToken\""
        }


class LivedoorBlogPoster:
    """
    ライブドアブログ投稿クラス

    AtomPub APIを使用して記事を投稿します。
    """

    # AtomPubの名前空間
    ATOM_NS = "http://www.w3.org/2005/Atom"
    APP_NS = "http://www.w3.org/2007/app"

    def __init__(
        self,
        blog_id: str,
        api_key: str,
        endpoint: str = "https://livedoor.blogcms.jp/atompub"
    ):
        """
        初期化

        Args:
            blog_id: ブログID（ライブドアブログのサブドメイン部分）
            api_key: AtomPub APIキー
            endpoint: AtomPubエンドポイント
        """
        self.blog_id = blog_id
        self.api_key = api_key
        self.endpoint = endpoint.rstrip("/")
        self.wsse = WSSEAuth(blog_id, api_key)
        self.session = requests.Session()

    def _get_headers(self) -> dict[str, str]:
        """
        HTTPリクエスト用ヘッダーを取得

        Returns:
            ヘッダー辞書
        """
        headers = {
            "Content-Type": "application/atom+xml; charset=utf-8",
            "Accept": "application/atom+xml",
        }
        headers.update(self.wsse.generate_header())
        return headers

    def _create_entry_xml(
        self,
        title: str,
        content: str,
        category: Optional[str] = None,
        draft: bool = True
    ) -> str:
        """
        Atom Entry XMLを生成

        Args:
            title: 記事タイトル
            content: 記事本文（HTML）
            category: カテゴリ名
            draft: 下書きとして保存するか

        Returns:
            Atom形式のXML文字列
        """
        # 名前空間の登録
        ET.register_namespace("", self.ATOM_NS)
        ET.register_namespace("app", self.APP_NS)

        # ルート要素を作成
        entry = ET.Element(f"{{{self.ATOM_NS}}}entry")

        # タイトル
        title_elem = ET.SubElement(entry, f"{{{self.ATOM_NS}}}title")
        title_elem.text = title

        # コンテンツ（HTMLとして）
        content_elem = ET.SubElement(entry, f"{{{self.ATOM_NS}}}content")
        content_elem.set("type", "html")
        content_elem.text = content

        # カテゴリ
        if category:
            category_elem = ET.SubElement(entry, f"{{{self.ATOM_NS}}}category")
            category_elem.set("term", category)

        # 下書き/公開状態
        control = ET.SubElement(entry, f"{{{self.APP_NS}}}control")
        draft_elem = ET.SubElement(control, f"{{{self.APP_NS}}}draft")
        draft_elem.text = "yes" if draft else "no"

        # XML文字列に変換
        xml_str = ET.tostring(entry, encoding="unicode", xml_declaration=False)

        # XML宣言を追加
        return f'<?xml version="1.0" encoding="utf-8"?>\n{xml_str}'

    def post_article(
        self,
        title: str,
        content: str,
        category: Optional[str] = None,
        draft: bool = True
    ) -> dict:
        """
        記事を投稿

        Args:
            title: 記事タイトル
            content: 記事本文（HTML）
            category: カテゴリ名
            draft: 下書きとして保存するか

        Returns:
            投稿結果を含む辞書
            {
                "success": bool,
                "article_id": str or None,
                "article_url": str or None,
                "error": str or None
            }

        Raises:
            requests.RequestException: 通信エラー
        """
        # 投稿先URL
        post_url = f"{self.endpoint}/{self.blog_id}/article"

        # Entry XMLを生成
        entry_xml = self._create_entry_xml(
            title=title,
            content=content,
            category=category,
            draft=draft
        )

        try:
            # POSTリクエスト
            response = self.session.post(
                post_url,
                data=entry_xml.encode("utf-8"),
                headers=self._get_headers(),
                timeout=30
            )

            # ステータスコードの確認
            if response.status_code == 201:
                # 成功（Created）
                return self._parse_response(response)

            elif response.status_code == 401:
                # 認証エラー
                return {
                    "success": False,
                    "article_id": None,
                    "article_url": None,
                    "error": "認証に失敗しました。APIキーとブログIDを確認してください。"
                }

            elif response.status_code == 403:
                # アクセス拒否
                return {
                    "success": False,
                    "article_id": None,
                    "article_url": None,
                    "error": "アクセスが拒否されました。APIの利用権限を確認してください。"
                }

            else:
                # その他のエラー
                return {
                    "success": False,
                    "article_id": None,
                    "article_url": None,
                    "error": f"投稿に失敗しました (HTTP {response.status_code}): {response.text[:200]}"
                }

        except requests.Timeout:
            return {
                "success": False,
                "article_id": None,
                "article_url": None,
                "error": "タイムアウトしました。ネットワーク接続を確認してください。"
            }
        except requests.RequestException as e:
            return {
                "success": False,
                "article_id": None,
                "article_url": None,
                "error": f"通信エラー: {str(e)}"
            }

    def _parse_response(self, response: requests.Response) -> dict:
        """
        投稿成功時のレスポンスを解析

        Args:
            response: HTTPレスポンス

        Returns:
            解析結果の辞書
        """
        try:
            # XMLを解析
            root = ET.fromstring(response.content)

            # 記事ID（edit linkから抽出）
            article_id = None
            article_url = None

            for link in root.findall(f".//{{{self.ATOM_NS}}}link"):
                rel = link.get("rel", "")
                href = link.get("href", "")

                if rel == "edit":
                    # edit URLから記事IDを抽出
                    article_id = href.split("/")[-1]
                elif rel == "alternate":
                    # 記事のURL
                    article_url = href

            return {
                "success": True,
                "article_id": article_id,
                "article_url": article_url,
                "error": None
            }

        except ET.ParseError:
            # XML解析に失敗してもCreatedが返っていれば成功とみなす
            return {
                "success": True,
                "article_id": None,
                "article_url": None,
                "error": None
            }

    def get_articles(self, limit: int = 10) -> list[dict]:
        """
        既存の記事一覧を取得

        Args:
            limit: 取得する記事数

        Returns:
            記事情報のリスト
        """
        list_url = f"{self.endpoint}/{self.blog_id}/article"

        try:
            response = self.session.get(
                list_url,
                headers=self._get_headers(),
                timeout=30
            )

            if response.status_code != 200:
                return []

            # XMLを解析
            root = ET.fromstring(response.content)
            articles = []

            for entry in root.findall(f".//{{{self.ATOM_NS}}}entry"):
                title_elem = entry.find(f"{{{self.ATOM_NS}}}title")
                title = title_elem.text if title_elem is not None else "無題"

                # IDを取得
                article_id = None
                article_url = None
                for link in entry.findall(f"{{{self.ATOM_NS}}}link"):
                    rel = link.get("rel", "")
                    href = link.get("href", "")

                    if rel == "edit":
                        article_id = href.split("/")[-1]
                    elif rel == "alternate":
                        article_url = href

                articles.append({
                    "id": article_id,
                    "title": title,
                    "url": article_url
                })

                if len(articles) >= limit:
                    break

            return articles

        except (requests.RequestException, ET.ParseError):
            return []

    def test_connection(self) -> dict:
        """
        接続テスト

        APIへの接続が正常かどうかを確認します。

        Returns:
            テスト結果の辞書
        """
        list_url = f"{self.endpoint}/{self.blog_id}/article"

        try:
            response = self.session.get(
                list_url,
                headers=self._get_headers(),
                timeout=10
            )

            if response.status_code == 200:
                return {
                    "success": True,
                    "message": "接続テスト成功"
                }
            elif response.status_code == 401:
                return {
                    "success": False,
                    "message": "認証に失敗しました。APIキーを確認してください。"
                }
            else:
                return {
                    "success": False,
                    "message": f"接続に失敗しました (HTTP {response.status_code})"
                }

        except requests.RequestException as e:
            return {
                "success": False,
                "message": f"接続エラー: {str(e)}"
            }


# 単体テスト用
if __name__ == "__main__":
    # WSSE認証のテスト
    wsse = WSSEAuth("test_user", "test_api_key")
    headers = wsse.generate_header()
    print("WSSE認証ヘッダー生成テスト:")
    print(f"  X-WSSE: {headers['X-WSSE'][:50]}...")
    print(f"  Authorization: {headers['Authorization']}")

    # Entry XML生成テスト
    poster = LivedoorBlogPoster("test_blog", "test_key")
    xml = poster._create_entry_xml(
        title="テスト記事",
        content="<p>これはテストです</p>",
        category="テスト",
        draft=True
    )
    print("\nEntry XML生成テスト:")
    print(xml[:200] + "...")
