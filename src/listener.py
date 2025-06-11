"""Monitor Twitter for keywords or account mentions."""

from __future__ import annotations

import logging
import os
import pickle
import urllib.parse
from dataclasses import dataclass
from typing import Iterable, List, Set

import pandas as pd
import requests
from selenium import webdriver
from selenium.common.exceptions import WebDriverException
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait


LOG = logging.getLogger(__name__)


@dataclass
class Tweet:
    """Simple representation of a tweet."""

    id: str
    text: str
    url: str


class TweetListener:
    """Scrape Twitter search results and notify for new tweets."""

    def __init__(
        self,
        driver: webdriver.Remote,
        query: str,
        *,
        cookies_file: str = "twitter_cookies.pkl",
        state_file: str = "listener_seen.csv",
        webhook: str | None = None,
    ) -> None:
        self.driver = driver
        self.query = query
        self.cookies_file = cookies_file
        self.state_file = state_file
        self.webhook = webhook or os.environ.get("SLACK_WEBHOOK_URL")
        self._seen: Set[str] = set()

    # ------------------------------------------------------------------
    # Session management
    # ------------------------------------------------------------------
    def load_session(self) -> None:
        """Load cookies if available, otherwise prompt for login."""
        self.driver.get("https://twitter.com/home")
        if os.path.exists(self.cookies_file):
            LOG.info("Loading cookies from %s", self.cookies_file)
            try:
                with open(self.cookies_file, "rb") as fh:
                    cookies = pickle.load(fh)
                for cookie in cookies:
                    cookie.pop("sameSite", None)
                    self.driver.add_cookie(cookie)
                self.driver.refresh()
                return
            except Exception as exc:  # pragma: no cover - best effort
                LOG.warning("Failed to load cookies: %s", exc)

        LOG.info("No valid cookies found. Please log in manually.")
        input("After logging in, press Enter to continue...")
        with open(self.cookies_file, "wb") as fh:
            pickle.dump(self.driver.get_cookies(), fh)
        LOG.info("Cookies saved to %s", self.cookies_file)

    # ------------------------------------------------------------------
    # Persistence helpers
    # ------------------------------------------------------------------
    def _load_state(self) -> None:
        if os.path.exists(self.state_file):
            try:
                df = pd.read_csv(self.state_file)
                self._seen = set(df["tweet_id"].astype(str))
            except Exception as exc:  # pragma: no cover - best effort
                LOG.warning("Failed to load state: %s", exc)

    def _save_state(self) -> None:
        try:
            pd.DataFrame({"tweet_id": list(self._seen)}).to_csv(
                self.state_file, index=False
            )
        except Exception as exc:  # pragma: no cover - best effort
            LOG.warning("Failed to save state: %s", exc)

    # ------------------------------------------------------------------
    # Scraping helpers
    # ------------------------------------------------------------------
    def _scrape(self) -> List[Tweet]:
        """Return new tweets matching the query."""
        encoded = urllib.parse.quote(self.query)
        url = f"https://twitter.com/search?q={encoded}&f=live"
        LOG.debug("Navigating to %s", url)
        self.driver.get(url)
        WebDriverWait(self.driver, 15).until(
            EC.presence_of_element_located((By.TAG_NAME, "article"))
        )

        tweets: List[Tweet] = []
        articles = self.driver.find_elements(By.CSS_SELECTOR, "article")
        for art in articles:
            try:
                link = art.find_element(By.CSS_SELECTOR, "a[href*='/status/']")
                url = link.get_attribute("href")
                tweet_id = url.split("/status/")[-1].split("?")[0]
                if tweet_id in self._seen:
                    continue
                text = art.text
                tweets.append(Tweet(tweet_id, text, url))
                self._seen.add(tweet_id)
            except Exception:  # pragma: no cover - best effort
                continue

        if tweets:
            self._save_state()
        return tweets

    # ------------------------------------------------------------------
    # Notification helper
    # ------------------------------------------------------------------
    def _notify(self, tweets: Iterable[Tweet]) -> None:
        for tw in tweets:
            message = f"New tweet found:\n{tw.text}\n{tw.url}"
            if self.webhook:
                try:
                    requests.post(self.webhook, json={"text": message}, timeout=5)
                except Exception as exc:  # pragma: no cover - best effort
                    LOG.warning("Failed to post to Slack: %s", exc)
            else:
                print(message)

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------
    def listen_once(self) -> None:
        self._load_state()
        new_tweets = self._scrape()
        self._notify(new_tweets)


def listen(query: str = "") -> None:
    """Entry point used by ``main.py`` to monitor Twitter."""
    if not query:
        query = input("Enter search query: ")

    driver = webdriver.Chrome()
    try:
        listener = TweetListener(driver, query)
        listener.load_session()
        listener.listen_once()
    finally:
        driver.quit()

