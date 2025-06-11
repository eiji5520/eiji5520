"""Manage following and unfollowing users."""

from __future__ import annotations

import datetime as _dt
import logging
import os
import pickle
from typing import Iterable, List, Tuple

import pandas as pd
from selenium import webdriver
from selenium.common.exceptions import WebDriverException
import time
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait


LOG = logging.getLogger(__name__)


class FollowerManager:
    """Handle follower management workflow."""

    def __init__(
        self,
        driver: webdriver.Remote,
        username: str,
        cookies_file: str = "twitter_cookies.pkl",
        data_file: str = "unfollowers.csv",
    ) -> None:
        self.driver = driver
        self.username = username
        self.cookies_file = cookies_file
        self.data_file = data_file

    # ------------------------------------------------------------------
    # Session management
    # ------------------------------------------------------------------
    def load_session(self) -> None:
        """Load cookies if available or wait for manual login."""
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
    # Data helpers
    # ------------------------------------------------------------------
    def _load_data(self) -> pd.DataFrame:
        if os.path.exists(self.data_file):
            return pd.read_csv(self.data_file, parse_dates=["first_seen"])
        return pd.DataFrame(columns=["username", "first_seen"])

    def _save_data(self, df: pd.DataFrame) -> None:
        df.to_csv(self.data_file, index=False)

    # ------------------------------------------------------------------
    # Scraping helpers
    # ------------------------------------------------------------------
    def _scroll_collect(self) -> List[Tuple[str, bool]]:
        """Return list of (username, follows_back) tuples from Following page."""
        self.driver.get(f"https://twitter.com/{self.username}/following")
        time_wait = WebDriverWait(self.driver, 15)
        time_wait.until(EC.presence_of_element_located((By.TAG_NAME, "body")))

        seen: set[str] = set()
        data: List[Tuple[str, bool]] = []
        last_height = -1

        while True:
            cards = self.driver.find_elements(By.CSS_SELECTOR, "div[dir='ltr'] a[href^='/%']")
            for card in cards:
                handle = card.get_attribute("href").split("/")[-1]
                if handle and handle not in seen:
                    seen.add(handle)
                    follows_back = bool(
                        card.find_elements(By.XPATH, ".//span[text()='Follows you']")
                    )
                    data.append((handle, follows_back))

            self.driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
            time.sleep(1)
            height = self.driver.execute_script("return document.body.scrollHeight")
            if height == last_height:
                break
            last_height = height
        return data

    # ------------------------------------------------------------------
    # Main logic
    # ------------------------------------------------------------------
    def find_unfollowers(self) -> List[str]:
        """Return users who haven't followed back for over 7 days."""
        info = self._scroll_collect()
        df = self._load_data()

        now = _dt.datetime.now()

        # Remove any that started following back
        for username, follows_back in info:
            if follows_back:
                df = df[df.username != username]
            else:
                if username not in df.username.values:
                    df = pd.concat(
                        [df, pd.DataFrame([{"username": username, "first_seen": now}])],
                        ignore_index=True,
                    )

        self._save_data(df)

        cutoff = now - _dt.timedelta(days=7)
        return df[df.first_seen <= cutoff].username.tolist()

    def unfollow(self, usernames: Iterable[str]) -> None:
        """Unfollow each username after confirmation."""
        for user in usernames:
            resp = input(f"Unfollow @{user}? [y/N] ")
            if resp.strip().lower() != "y":
                continue
            try:
                self.driver.get(f"https://twitter.com/{user}")
                btn = WebDriverWait(self.driver, 15).until(
                    EC.element_to_be_clickable(
                        (By.CSS_SELECTOR, "div[data-testid='unfollow']")
                    )
                )
                btn.click()
                confirm = WebDriverWait(self.driver, 5).until(
                    EC.element_to_be_clickable(
                        (By.CSS_SELECTOR, "div[data-testid='confirmationSheetConfirm']")
                    )
                )
                confirm.click()
                LOG.info("Unfollowed %s", user)
            except Exception as exc:  # pragma: no cover - best effort
                LOG.warning("Failed to unfollow %s: %s", user, exc)
                try:
                    self.driver.find_element(By.TAG_NAME, "body").send_keys(Keys.ESCAPE)
                except WebDriverException:
                    pass


def manage_followers(username: str = "") -> None:
    """Entry point called by ``main.py``."""
    if not username:
        username = input("Enter your Twitter username: ").lstrip("@")

    driver = webdriver.Chrome()
    try:
        mgr = FollowerManager(driver, username)
        mgr.load_session()
        candidates = mgr.find_unfollowers()
        if not candidates:
            print("No unfollow candidates found.")
            return
        print("Users not following back for over 7 days:")
        for c in candidates:
            print(f" - @{c}")
        mgr.unfollow(candidates)
    finally:
        driver.quit()

