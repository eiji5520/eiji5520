import os
import json
from pathlib import Path
from typing import Optional

from playwright.sync_api import sync_playwright, Browser, BrowserContext, Page


class InstagramClient:
    """Playwright を利用したシンプルな Instagram クライアント。"""

    def __init__(self, proxy_config: str = "proxies.json", session_file: str = "session.json") -> None:
        self.proxy_config = Path(proxy_config)
        self.session_file = Path(session_file)
        self.browser: Optional[Browser] = None
        self.context: Optional[BrowserContext] = None
        self.page: Optional[Page] = None
        self.current_proxy: Optional[str] = None

    def _load_proxies(self) -> list[str]:
        if not self.proxy_config.exists():
            return []
        try:
            data = json.loads(self.proxy_config.read_text())
            return data.get("proxies", [])
        except Exception:
            return []

    def _rotate_proxy(self) -> Optional[str]:
        proxies = self._load_proxies()
        if not proxies:
            return None
        proxy = proxies.pop(0)
        proxies.append(proxy)
        self.proxy_config.write_text(json.dumps({"proxies": proxies}, indent=2))
        return proxy

    def set_proxy(self) -> None:
        """Rotate and set the next available proxy."""
        self.current_proxy = self._rotate_proxy()

    def login(self, retries: int = 3) -> None:
        """Instagram にログインする。失敗した場合は指定回数まで再試行する。"""

        username = os.getenv("INSTA_USER")
        password = os.getenv("INSTA_PASSWORD")
        if not username or not password:
            raise EnvironmentError("INSTA_USER and INSTA_PASSWORD environment variables must be set")

        for attempt in range(1, retries + 1):
            try:
                with sync_playwright() as p:
                    self.set_proxy()
                    launch_args = {}
                    if self.current_proxy:
                        launch_args["proxy"] = {"server": self.current_proxy}
                    browser = p.chromium.launch(headless=True, **launch_args)
                    context_args = {}
                    if self.session_file.exists():
                        context_args["storage_state"] = str(self.session_file)
                    context = browser.new_context(**context_args)
                    page = context.new_page()
                    page.goto("https://www.instagram.com/", wait_until="networkidle")
                    if "accounts/login" in page.url:
                        page.fill("input[name='username']", username)
                        page.fill("input[name='password']", password)
                        page.click("button[type='submit']")
                        page.wait_for_load_state("networkidle")
                        if "accounts/login" in page.url:
                            raise RuntimeError("Login failed")
                    context.storage_state(path=str(self.session_file))
                    browser.close()
                    return
            except Exception:
                if attempt == retries:
                    raise

    def save_session(self) -> None:
        """Save session cookies to a file."""
        if self.context:
            self.context.storage_state(path=str(self.session_file))

    def upload_content(self, media_path: str, caption: str, media_type: str = "feed") -> str:
        """画像や動画をアップロードして投稿する。"""
        if media_type not in {"feed", "story", "reel"}:
            raise ValueError("media_type must be 'feed', 'story', or 'reel'")

        with sync_playwright() as p:
            self.set_proxy()
            launch_args = {}
            if self.current_proxy:
                launch_args["proxy"] = {"server": self.current_proxy}
            browser = p.chromium.launch(headless=True, **launch_args)
            context_args = {}
            if self.session_file.exists():
                context_args["storage_state"] = str(self.session_file)
            context = browser.new_context(**context_args)
            page = context.new_page()

            page.goto("https://www.instagram.com/", wait_until="networkidle")
            page.click("svg[aria-label='New post']")
            page.set_input_files("input[type='file']", media_path)
            page.click("text=Next")
            if media_type != "story":
                page.click("text=Next")
                if caption:
                    page.fill("textarea[aria-label='Write a caption…']", caption)
            page.click("text=Share")
            page.wait_for_load_state("networkidle")

            context.storage_state(path=str(self.session_file))
            browser.close()
            return "posted"

