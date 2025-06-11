import PySimpleGUI as sg
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.common.exceptions import WebDriverException
import schedule
import threading
import time
import random
import logging
import csv
import os
from datetime import datetime


logging.basicConfig(
    filename='sns_manager.log',
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)


class SNSManager:
    def __init__(self, driver_path=None):
        options = webdriver.ChromeOptions()
        options.add_argument('--headless')
        try:
            if driver_path:
                self.driver = webdriver.Chrome(executable_path=driver_path, options=options)
            else:
                self.driver = webdriver.Chrome(options=options)
        except WebDriverException as e:
            logging.error(f"WebDriver initialization failed: {e}")
            sg.popup_error('WebDriver not found. Please install ChromeDriver and check PATH.')
            raise
        self.logged_in = False
        self.follower_history_file = 'follower_history.csv'
        if not os.path.exists(self.follower_history_file):
            with open(self.follower_history_file, 'w', newline='') as f:
                writer = csv.writer(f)
                writer.writerow(['timestamp', 'followers'])

    def random_delay(self, min_sec=2, max_sec=5):
        time.sleep(random.uniform(min_sec, max_sec))

    def login(self, url, username, password):
        try:
            self.driver.get(url)
            self.random_delay()
            # The following selectors are placeholders and need to be adjusted
            self.driver.find_element(By.NAME, 'session[username_or_email]').send_keys(username)
            self.driver.find_element(By.NAME, 'session[password]').send_keys(password)
            self.random_delay()
            self.driver.find_element(By.CSS_SELECTOR, 'div[data-testid="LoginForm_Login_Button"]').click()
            self.random_delay()
            self.logged_in = True
            logging.info('Logged in successfully')
        except Exception as e:
            logging.error(f"Login failed: {e}")
            sg.popup_error(f'Login failed: {e}')

    def post_now(self, text):
        if not self.logged_in:
            sg.popup_error('Not logged in')
            return
        try:
            # Placeholder: navigate to posting area and submit text
            logging.info(f'Attempting to post: {text}')
            self.random_delay()
            # Example selectors; change to match the SNS
            self.driver.find_element(By.CSS_SELECTOR, 'div.public-DraftStyleDefault-block').send_keys(text)
            self.random_delay()
            self.driver.find_element(By.CSS_SELECTOR, 'div[data-testid="tweetButtonInline"]').click()
            logging.info('Post submitted')
        except Exception as e:
            logging.error(f"Post failed: {e}")
            sg.popup_error(f'Post failed: {e}')

    def schedule_post(self, text, post_time: datetime):
        def job():
            self.post_now(text)
        delay = (post_time - datetime.now()).total_seconds()
        if delay < 0:
            sg.popup_error('Time already passed')
            return
        schedule.every(delay).seconds.do(job)
        logging.info(f'Scheduled post "{text}" at {post_time}')

    def follow(self, target_username):
        if not self.logged_in:
            sg.popup_error('Not logged in')
            return
        try:
            logging.info(f'Attempting to follow {target_username}')
            self.driver.get(f'https://twitter.com/{target_username}')
            self.random_delay()
            self.driver.find_element(By.CSS_SELECTOR, 'div[data-testid="placementTracking"] span').click()
            logging.info(f'Followed {target_username}')
        except Exception as e:
            logging.error(f"Follow failed: {e}")
            sg.popup_error(f'Follow failed: {e}')

    def unfollow(self, target_username):
        if not self.logged_in:
            sg.popup_error('Not logged in')
            return
        try:
            logging.info(f'Attempting to unfollow {target_username}')
            self.driver.get(f'https://twitter.com/{target_username}')
            self.random_delay()
            self.driver.find_element(By.CSS_SELECTOR, 'div[data-testid="placementTracking"] span').click()
            self.random_delay()
            self.driver.find_element(By.CSS_SELECTOR, 'div[data-testid="confirmationSheetConfirm"]').click()
            logging.info(f'Unfollowed {target_username}')
        except Exception as e:
            logging.error(f"Unfollow failed: {e}")
            sg.popup_error(f'Unfollow failed: {e}')

    def get_follower_count(self, username):
        try:
            self.driver.get(f'https://twitter.com/{username}')
            self.random_delay()
            followers = self.driver.find_element(By.CSS_SELECTOR, 'a[href$="/followers"] span span').text
            logging.info(f'Follower count for {username}: {followers}')
            with open(self.follower_history_file, 'a', newline='') as f:
                writer = csv.writer(f)
                writer.writerow([datetime.now().isoformat(), followers])
            return followers
        except Exception as e:
            logging.error(f"Failed to get follower count: {e}")
            sg.popup_error(f'Failed to get follower count: {e}')
            return '0'


def run_schedule():
    while True:
        schedule.run_pending()
        time.sleep(1)


def main():
    manager = None
    threading.Thread(target=run_schedule, daemon=True).start()

    sg.theme('DefaultNoMoreNagging')
    layout = [
        [sg.Text('SNS Login')],
        [sg.Text('Login URL'), sg.Input('https://twitter.com/login', key='-URL-')],
        [sg.Text('Username'), sg.Input(key='-USER-')],
        [sg.Text('Password'), sg.Input(password_char='*', key='-PASS-')],
        [sg.Button('Login')],
        [sg.HorizontalSeparator()],
        [sg.Text('Post Text')],
        [sg.Multiline(key='-POST_TEXT-', size=(50, 5))],
        [sg.Button('Post Now'), sg.Button('Schedule Post')],
        [sg.Text('Schedule Time (YYYY-MM-DD HH:MM)', key='-SCHED_LABEL-')],
        [sg.Input(key='-SCHED_TIME-')],
        [sg.HorizontalSeparator()],
        [sg.Text('Follow / Unfollow User')],
        [sg.Input(key='-TARGET_USER-')],
        [sg.Button('Follow'), sg.Button('Unfollow')],
        [sg.HorizontalSeparator()],
        [sg.Button('Get Follower Count'), sg.Text('', key='-FOLLOWERS-')],
        [sg.Button('Exit')]
    ]

    window = sg.Window('SNS Manager', layout)

    while True:
        event, values = window.read(timeout=100)
        if event == sg.WINDOW_CLOSED or event == 'Exit':
            break
        if event == 'Login':
            try:
                manager = SNSManager()
                manager.login(values['-URL-'], values['-USER-'], values['-PASS-'])
            except Exception:
                manager = None
        if event == 'Post Now' and manager:
            manager.post_now(values['-POST_TEXT-'])
        if event == 'Schedule Post' and manager:
            try:
                post_time = datetime.strptime(values['-SCHED_TIME-'], '%Y-%m-%d %H:%M')
                manager.schedule_post(values['-POST_TEXT-'], post_time)
                sg.popup('Post scheduled')
            except ValueError:
                sg.popup_error('Invalid date format')
        if event == 'Follow' and manager:
            manager.follow(values['-TARGET_USER-'])
        if event == 'Unfollow' and manager:
            manager.unfollow(values['-TARGET_USER-'])
        if event == 'Get Follower Count' and manager:
            count = manager.get_follower_count(values['-TARGET_USER-'])
            window['-FOLLOWERS-'].update(count)

    if manager and manager.driver:
        manager.driver.quit()
    window.close()


if __name__ == '__main__':
    main()
