"""Module for tweet scheduling."""

from __future__ import annotations

import datetime as _dt
import logging
import os
import pickle
import time
from typing import Iterable

import pandas as pd
from selenium import webdriver
from selenium.common.exceptions import WebDriverException
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait


LOG = logging.getLogger(__name__)


class TweetScheduler:
    """Scheduler that posts tweets based on timestamps from a CSV."""

    def __init__(self, driver: webdriver.Remote, cookies_file: str = "twitter_cookies.pkl") -> None:
        self.driver = driver
        self.cookies_file = cookies_file

    # ------------------------------------------------------------------
    # Session management
    # ------------------------------------------------------------------
    def load_session(self) -> None:
        """Load cookies if available, otherwise prompt for manual login."""
        self.driver.get("https://twitter.com/home")
        if os.path.exists(self.cookies_file):
            LOG.info("Loading cookies from %s", self.cookies_file)
            try:
                with open(self.cookies_file, "rb") as fh:
                    cookies = pickle.load(fh)
                for cookie in cookies:
                    # Some cookies might be missing the `sameSite` attribute
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
    # Tweet posting logic
    # ------------------------------------------------------------------
    def _post_tweet(self, text: str) -> bool:
        """Navigate to compose page and attempt to post a tweet."""
        for attempt in range(1, 4):
            try:
                LOG.debug("Posting tweet attempt %d", attempt)
                self.driver.get("https://twitter.com/compose/tweet")

                textarea = WebDriverWait(self.driver, 15).until(
                    EC.presence_of_element_located((By.CSS_SELECTOR, 'div[aria-label="Tweet text"]'))
                )
                textarea.clear()
                textarea.send_keys(text)

                tweet_btn = self.driver.find_element(By.CSS_SELECTOR, 'div[data-testid="tweetButtonInline"]')
                tweet_btn.click()
                LOG.info("Successfully posted tweet: %s", text)
                return True
            except Exception as exc:  # pragma: no cover - best effort
                LOG.warning("Error posting tweet: %s", exc)
                try:
                    self.driver.find_element(By.TAG_NAME, "body").send_keys(Keys.ESCAPE)
                except WebDriverException:
                    pass
                time.sleep(5)
        LOG.error("Failed to post tweet after 3 attempts: %s", text)
        return False

    def post_from_rows(self, rows: Iterable[tuple[_dt.datetime, str]]) -> None:
        """Iterate over rows of (datetime, content) and post them."""
        for ts, content in rows:
            wait = (ts - _dt.datetime.now()).total_seconds()
            if wait > 0:
                time.sleep(wait)
            self._post_tweet(content)

    def post_from_csv(self, csv_path: str) -> None:
        """Read CSV with columns [datetime, content] and post tweets."""
        LOG.info("Loading tweets from %s", csv_path)
        df = pd.read_csv(csv_path, parse_dates=["datetime"])
        df = df.sort_values("datetime")
        rows = ((row["datetime"].to_pydatetime(), row["content"]) for _, row in df.iterrows())
        self.post_from_rows(rows)


def post_scheduled_tweets(csv_path: str = "tweets.csv") -> None:
    """Convenience function used by ``main.py`` to post tweets."""
    driver = webdriver.Chrome()
    try:
        sched = TweetScheduler(driver)
        sched.load_session()
        sched.post_from_csv(csv_path)
    finally:
        driver.quit()
