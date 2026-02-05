#!/usr/bin/env python3
"""
5ちゃんねるまとめブログ自動投稿ツール

メインエントリーポイント

このツールは5ちゃんねるのスレッドを取得し、
まとめサイト風のHTMLに整形してライブドアブログに投稿します。
"""

import argparse
import os
import sys
from pathlib import Path

# 環境変数の読み込み
from dotenv import load_dotenv

# 自作モジュール
from scraper import ThreadScraper
from poster import LivedoorBlogPoster


def load_config() -> dict:
    """
    環境変数から設定を読み込む

    Returns:
        設定値の辞書

    Raises:
        ValueError: 必須の設定が不足している場合
    """
    # .envファイルを読み込む（存在する場合）
    env_path = Path(__file__).parent / ".env"
    load_dotenv(env_path)

    config = {
        # Livedoor Blog設定
        "blog_id": os.getenv("LIVEDOOR_BLOG_ID", ""),
        "api_key": os.getenv("LIVEDOOR_API_KEY", ""),
        "endpoint": os.getenv("LIVEDOOR_ENDPOINT", "https://livedoor.blogcms.jp/atompub"),

        # 投稿設定
        "post_status": os.getenv("POST_STATUS", "draft"),
        "default_category": os.getenv("DEFAULT_CATEGORY", "まとめ"),

        # スクレイピング設定
        "request_delay": float(os.getenv("REQUEST_DELAY", "1.0")),
        "user_agent": os.getenv("USER_AGENT", ""),
    }

    return config


def validate_config(config: dict, require_api: bool = True) -> list[str]:
    """
    設定値を検証

    Args:
        config: 設定値の辞書
        require_api: API設定の検証が必要かどうか

    Returns:
        エラーメッセージのリスト（空なら問題なし）
    """
    errors = []

    if require_api:
        if not config["blog_id"]:
            errors.append("LIVEDOOR_BLOG_ID が設定されていません")
        if not config["api_key"]:
            errors.append("LIVEDOOR_API_KEY が設定されていません")

    return errors


def scrape_thread(url: str, config: dict, first_n: int = 10, min_anchor: int = 2) -> tuple[str, str]:
    """
    スレッドをスクレイピング

    Args:
        url: スレッドURL
        config: 設定値
        first_n: 最初から含めるレス数
        min_anchor: 人気とみなす最小アンカー数

    Returns:
        (タイトル, HTML) のタプル
    """
    # スクレイパーを初期化
    scraper = ThreadScraper(
        user_agent=config["user_agent"] or None,
        request_delay=config["request_delay"]
    )

    print(f"スレッドを取得中: {url}")

    # スクレイピング実行
    title, html = scraper.scrape(
        url,
        include_first_n=first_n,
        min_anchor_count=min_anchor
    )

    print(f"タイトル: {title}")

    return title, html


def post_to_blog(title: str, content: str, config: dict, draft: bool = True) -> dict:
    """
    ブログに投稿

    Args:
        title: 記事タイトル
        content: 記事本文（HTML）
        config: 設定値
        draft: 下書きとして保存するか

    Returns:
        投稿結果の辞書
    """
    # ポスターを初期化
    poster = LivedoorBlogPoster(
        blog_id=config["blog_id"],
        api_key=config["api_key"],
        endpoint=config["endpoint"]
    )

    status_text = "下書き" if draft else "公開"
    print(f"ブログに{status_text}として投稿中...")

    # 投稿実行
    result = poster.post_article(
        title=title,
        content=content,
        category=config["default_category"],
        draft=draft
    )

    return result


def cmd_scrape(args, config: dict) -> int:
    """
    scrapeコマンドの処理（スクレイピングのみ）

    Args:
        args: コマンドライン引数
        config: 設定値

    Returns:
        終了コード
    """
    try:
        title, html = scrape_thread(
            args.url,
            config,
            first_n=args.first_n,
            min_anchor=args.min_anchor
        )

        # 出力
        if args.output:
            # ファイルに保存
            output_path = Path(args.output)
            output_path.write_text(html, encoding="utf-8")
            print(f"HTMLを保存しました: {output_path}")
        else:
            # 標準出力
            print("\n--- 生成されたHTML ---")
            print(html)

        return 0

    except Exception as e:
        print(f"エラー: {e}", file=sys.stderr)
        return 1


def cmd_post(args, config: dict) -> int:
    """
    postコマンドの処理（スクレイピング＋投稿）

    Args:
        args: コマンドライン引数
        config: 設定値

    Returns:
        終了コード
    """
    # 設定検証
    errors = validate_config(config, require_api=True)
    if errors:
        print("設定エラー:", file=sys.stderr)
        for error in errors:
            print(f"  - {error}", file=sys.stderr)
        print("\n.envファイルを確認してください。", file=sys.stderr)
        return 1

    try:
        # スクレイピング
        title, html = scrape_thread(
            args.url,
            config,
            first_n=args.first_n,
            min_anchor=args.min_anchor
        )

        # 投稿状態の決定
        draft = not args.publish

        # ブログに投稿
        result = post_to_blog(title, html, config, draft=draft)

        if result["success"]:
            print("✓ 投稿成功!")
            if result["article_url"]:
                print(f"  記事URL: {result['article_url']}")
            if result["article_id"]:
                print(f"  記事ID: {result['article_id']}")
            return 0
        else:
            print(f"✗ 投稿失敗: {result['error']}", file=sys.stderr)
            return 1

    except Exception as e:
        print(f"エラー: {e}", file=sys.stderr)
        return 1


def cmd_test(args, config: dict) -> int:
    """
    testコマンドの処理（接続テスト）

    Args:
        args: コマンドライン引数
        config: 設定値

    Returns:
        終了コード
    """
    # 設定検証
    errors = validate_config(config, require_api=True)
    if errors:
        print("設定エラー:", file=sys.stderr)
        for error in errors:
            print(f"  - {error}", file=sys.stderr)
        return 1

    # ポスターを初期化
    poster = LivedoorBlogPoster(
        blog_id=config["blog_id"],
        api_key=config["api_key"],
        endpoint=config["endpoint"]
    )

    print("接続テストを実行中...")
    result = poster.test_connection()

    if result["success"]:
        print(f"✓ {result['message']}")

        # 最新記事を表示
        print("\n最新の記事:")
        articles = poster.get_articles(limit=5)
        if articles:
            for i, article in enumerate(articles, 1):
                print(f"  {i}. {article['title']}")
        else:
            print("  (記事がありません)")

        return 0
    else:
        print(f"✗ {result['message']}", file=sys.stderr)
        return 1


def main():
    """
    メインエントリーポイント
    """
    # メインパーサー
    parser = argparse.ArgumentParser(
        description="5ちゃんねるまとめブログ自動投稿ツール",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
使用例:
  # スレッドをスクレイピングしてHTMLを出力
  python main.py scrape https://mi.5ch.net/test/read.cgi/news4vip/xxxxx/

  # スレッドをスクレイピングしてファイルに保存
  python main.py scrape https://mi.5ch.net/test/read.cgi/news4vip/xxxxx/ -o output.html

  # スレッドをスクレイピングしてブログに下書き投稿
  python main.py post https://mi.5ch.net/test/read.cgi/news4vip/xxxxx/

  # スレッドをスクレイピングしてブログに公開投稿
  python main.py post https://mi.5ch.net/test/read.cgi/news4vip/xxxxx/ --publish

  # API接続テスト
  python main.py test
        """
    )

    # サブコマンド
    subparsers = parser.add_subparsers(dest="command", help="実行するコマンド")

    # scrapeコマンド
    scrape_parser = subparsers.add_parser("scrape", help="スレッドをスクレイピング（投稿なし）")
    scrape_parser.add_argument("url", help="5chスレッドのURL")
    scrape_parser.add_argument("-o", "--output", help="出力ファイルパス")
    scrape_parser.add_argument(
        "--first-n", type=int, default=10,
        help="最初から含めるレス数 (デフォルト: 10)"
    )
    scrape_parser.add_argument(
        "--min-anchor", type=int, default=2,
        help="人気とみなす最小アンカー数 (デフォルト: 2)"
    )

    # postコマンド
    post_parser = subparsers.add_parser("post", help="スレッドをスクレイピングしてブログに投稿")
    post_parser.add_argument("url", help="5chスレッドのURL")
    post_parser.add_argument(
        "--publish", action="store_true",
        help="公開状態で投稿（デフォルトは下書き）"
    )
    post_parser.add_argument(
        "--first-n", type=int, default=10,
        help="最初から含めるレス数 (デフォルト: 10)"
    )
    post_parser.add_argument(
        "--min-anchor", type=int, default=2,
        help="人気とみなす最小アンカー数 (デフォルト: 2)"
    )

    # testコマンド
    test_parser = subparsers.add_parser("test", help="API接続テスト")

    # 引数を解析
    args = parser.parse_args()

    # コマンドが指定されていない場合はヘルプを表示
    if not args.command:
        parser.print_help()
        return 0

    # 設定を読み込み
    config = load_config()

    # コマンドに応じた処理を実行
    if args.command == "scrape":
        return cmd_scrape(args, config)
    elif args.command == "post":
        return cmd_post(args, config)
    elif args.command == "test":
        return cmd_test(args, config)
    else:
        parser.print_help()
        return 1


if __name__ == "__main__":
    sys.exit(main())
