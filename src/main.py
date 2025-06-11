"""Main loop for Twitter automation."""
from . import scheduler, followers, listener, analytics
import schedule
import time


def setup_tasks():
    """Configure scheduled tasks."""
    schedule.every(1).hours.do(scheduler.post_scheduled_tweets)
    schedule.every(30).minutes.do(followers.manage_followers)
    schedule.every(10).minutes.do(listener.listen)
    schedule.every().day.at("00:00").do(analytics.collect_daily_stats)


def run_forever():
    """Run scheduled tasks continuously."""
    setup_tasks()
    while True:
        schedule.run_pending()
        time.sleep(1)


if __name__ == "__main__":
    run_forever()
