import os
import csv
import json
import random
import time
from datetime import datetime
from pathlib import Path
from typing import List, Optional

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC


def _load_proxies(proxy_config: Path) -> List[str]:
    if not proxy_config.exists():
        return []
    try:
        data = json.loads(proxy_config.read_text())
        return data.get("proxies", [])
    except Exception:
        return []


def _rotate_proxy(proxy_config: Path) -> Optional[str]:
    proxies = _load_proxies(proxy_config)
    if not proxies:
        return None
    proxy = proxies.pop(0)
    proxies.append(proxy)
    proxy_config.write_text(json.dumps({"proxies": proxies}, indent=2))
    return proxy


def _setup_driver(proxy: Optional[str]) -> webdriver.Chrome:
    options = Options()
    options.add_argument("--headless")
    if proxy:
        options.add_argument(f"--proxy-server={proxy}")
    driver = webdriver.Chrome(options=options)
    driver.implicitly_wait(10)
    return driver


def _login(driver: webdriver.Chrome, username: str, password: str) -> None:
    driver.get("https://www.instagram.com/accounts/login/")
    WebDriverWait(driver, 15).until(EC.presence_of_element_located((By.NAME, "username")))
    driver.find_element(By.NAME, "username").send_keys(username)
    driver.find_element(By.NAME, "password").send_keys(password)
    driver.find_element(By.NAME, "password").send_keys(Keys.RETURN)
    WebDriverWait(driver, 15).until(EC.presence_of_element_located((By.CSS_SELECTOR, "nav")))


def _load_more_posts(driver: webdriver.Chrome, minimum: int = 30) -> None:
    post_selector = "article div a"  # Anchor tags linking to posts
    posts = driver.find_elements(By.CSS_SELECTOR, post_selector)
    while len(posts) < minimum:
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        posts = driver.find_elements(By.CSS_SELECTOR, post_selector)


def interact_with_hashtags(
    hashtags: List[str],
    proxy_config: str = "proxies.json",
    log_file: str = "actions.csv",
) -> None:
    """Interact with posts for given hashtags using Selenium."""
    proxy_path = Path(proxy_config)
    proxy = _rotate_proxy(proxy_path)

    username = os.getenv("INSTA_USER")
    password = os.getenv("INSTA_PASSWORD")
    if not username or not password:
        raise EnvironmentError("INSTA_USER and INSTA_PASSWORD must be set")

    driver = _setup_driver(proxy)
    try:
        _login(driver, username, password)
        with open(log_file, "a", newline="") as csvfile:
            writer = csv.writer(csvfile)
            if csvfile.tell() == 0:
                writer.writerow(["timestamp", "hashtag", "action", "proxy"])

            for tag in hashtags:
                driver.get(f"https://www.instagram.com/explore/tags/{tag}/")
                _load_more_posts(driver, 30)
                links = [e.get_attribute("href") for e in driver.find_elements(By.CSS_SELECTOR, "article div a")][:30]

                for link in links:
                    driver.get(link)
                    try:
                        like_button = WebDriverWait(driver, 10).until(
                            EC.presence_of_element_located((By.CSS_SELECTOR, "svg[aria-label='Like']"))
                        )
                        like_button.find_element(By.XPATH, "..").click()
                        writer.writerow([datetime.utcnow().isoformat(), tag, "like", proxy or "" ])
                    except Exception:
                        pass  # Already liked or button not found

                    try:
                        author_link = driver.find_element(By.CSS_SELECTOR, "header a").get_attribute("href")
                        driver.get(author_link)
                        follower_elem = WebDriverWait(driver, 10).until(
                            EC.presence_of_element_located((By.PARTIAL_LINK_TEXT, "followers"))
                        )
                        follower_count = follower_elem.text.split()[0].replace(',', '')
                        follower_count = int(follower_count)
                        if follower_count < 5000:
                            follow_btn = driver.find_element(By.XPATH, "//button[text()='Follow']")
                            follow_btn.click()
                            writer.writerow([datetime.utcnow().isoformat(), tag, "follow", proxy or "" ])
                    except Exception:
                        pass

                    wait_time = random.randint(20, 40)
                    time.sleep(wait_time)
    finally:
        driver.quit()
