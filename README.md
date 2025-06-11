# SNS Manager

This is a simple desktop application for automating basic SNS operations such as login, posting, scheduling posts, following/unfollowing users and tracking follower counts.

## Features
- **Automatic Login** using Selenium.
- **Instant Posting** from the GUI.
- **Scheduled Posting** at a specific date and time (while the app is running).
- **Follow/Unfollow** automation.
- **Follower Count Tracking** stored locally and shown in the GUI.

The example selectors are written for Twitter and may need to be adjusted if Twitter changes its page structure.  If elements cannot be found during login, update the selectors in `sns_manager.py`.

## Requirements
- Python 3.8+
- Google Chrome and [ChromeDriver](https://chromedriver.chromium.org/) installed and available in your `PATH`.

Install Python dependencies:
```bash
pip install -r requirements.txt
```

## Usage
Run the application with:
```bash
python sns_manager.py
```

Enter your login information and use the buttons to perform actions. To schedule a post, enter the text and a time in `YYYY-MM-DD HH:MM` format.

Logs are written to `sns_manager.log`. Follower history is stored in `follower_history.csv`.
