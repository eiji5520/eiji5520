"""Collect tweet metrics and visualize trends using Selenium."""
from __future__ import annotations

import logging
import os
import pickle
from dataclasses import dataclass
from typing import List

import pandas as pd
import matplotlib.pyplot as plt
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait


LOG = logging.getLogger(__name__)


@dataclass
class TweetMetrics:
    """Simple record of tweet metrics."""

    tweet_id: str
    timestamp: pd.Timestamp
    impressions: int


class AnalyticsCollector:
    """Scrape tweet metrics and build aggregate stats."""

    def __init__(
        self,
        driver: webdriver.Remote,
        username: str,
        *,
        cookies_file: str = "twitter_cookies.pkl",
        data_file: str = "analytics.csv",
    ) -> None:
        self.driver = driver
        self.username = username
        self.cookies_file = cookies_file
        self.data_file = data_file

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
    # Scraping helpers
    # ------------------------------------------------------------------
    def _scrape_metrics(self) -> List[TweetMetrics]:
        """Return metrics for recent tweets."""
        LOG.debug("Collecting metrics from timeline")
        url = f"https://twitter.com/{self.username}"
        self.driver.get(url)
        WebDriverWait(self.driver, 15).until(
            EC.presence_of_element_located((By.TAG_NAME, "article"))
        )

        metrics: List[TweetMetrics] = []
        tweets = self.driver.find_elements(By.CSS_SELECTOR, "article")
        for art in tweets:
            try:
                link = art.find_element(By.CSS_SELECTOR, "a[href*='/status/']")
                t_url = link.get_attribute("href")
                tweet_id = t_url.split("/status/")[-1].split("?")[0]
                time_el = art.find_element(By.TAG_NAME, "time")
                ts = pd.to_datetime(time_el.get_attribute("datetime"))

                # Attempt to locate the impressions element
                imp_el = art.find_element(By.XPATH, "//*[contains(@aria-label, 'Views') or contains(@aria-label, 'views') or contains(@aria-label, 'インプレッション')]")
                imp_text = imp_el.get_attribute("aria-label") or imp_el.text
                impressions = int(''.join(c for c in imp_text if c.isdigit()))

                metrics.append(TweetMetrics(tweet_id, ts, impressions))
            except Exception:  # pragma: no cover - best effort
                continue
        return metrics

    def _append_to_csv(self, rows: List[TweetMetrics]) -> pd.DataFrame:
        df = pd.DataFrame(rows)
        if os.path.exists(self.data_file):
            existing = pd.read_csv(self.data_file, parse_dates=["timestamp"])
            df = pd.concat([existing, df]).drop_duplicates("tweet_id", keep="last")
        df.to_csv(self.data_file, index=False)
        return df

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------
    def collect(self) -> pd.DataFrame:
        rows = self._scrape_metrics()
        return self._append_to_csv(rows)

    def report(self, df: pd.DataFrame | None = None) -> None:
        if df is None:
            df = pd.read_csv(self.data_file, parse_dates=["timestamp"])
        if df.empty:
            LOG.warning("No analytics data available.")
            return
        df["date"] = df["timestamp"].dt.date
        daily = df.groupby("date")["impressions"].sum()

        plt.figure(figsize=(8, 4))
        daily.plot(marker="o")
        plt.title("Daily Tweet Impressions")
        plt.xlabel("Date")
        plt.ylabel("Impressions")
        plt.tight_layout()
        plt.savefig("analytics_report.png")
        daily.to_csv("analytics_summary.csv", header=["impressions"])
        LOG.info("Saved analytics_report.png and analytics_summary.csv")


def collect_daily_stats(username: str = "") -> None:
    """Entry point used by ``main.py`` to gather analytics."""
    if not username:
        username = input("Enter your Twitter username: ").lstrip("@")

    driver = webdriver.Chrome()
    try:
        collector = AnalyticsCollector(driver, username)
        collector.load_session()
        df = collector.collect()
        collector.report(df)
    finally:
        driver.quit()
