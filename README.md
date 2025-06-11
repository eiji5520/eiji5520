# Twitter Automation Skeleton

This project provides a basic skeleton for automating Twitter operations without using the official API. It uses Selenium to control the web browser and is designed to run continuously while the PC is on.

## Features
- **Post Scheduling**: Schedule tweets to be posted at specific times.
- **Follower Management**: Automate following/unfollowing based on custom logic.
- **Social Listening**: Monitor keywords or hashtags on Twitter.
- **Analytics**: Collect simple metrics from Twitter pages.
- **Desktop Dashboard**: PySimpleGUI interface for managing schedules, followers, and viewing logs.

Each feature is represented by a separate module in the `src` directory. You will need to implement your own logic for interacting with the Twitter web interface.

## Setup
1. Install dependencies:
   ```bash
   pip install -r requirements.txt
   ```
2. Run the automation loop:
   ```bash
   python -m src.main
   ```
3. Launch the dashboard interface:
   ```bash
   python -m src.dashboard_gui
   ```
   Use the **File → Login** menu option to open a browser and log in to Twitter
   so cookies are saved for automated tasks. Within the *Schedules* tab you can
   add tweets with the desired datetime and content.

**Note:** Selenium requires a compatible web driver (e.g., ChromeDriver or GeckoDriver) installed and available in your system PATH.
