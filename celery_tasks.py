from __future__ import annotations

import os
from datetime import datetime

from celery import Celery
import psycopg2

from instagram_client import InstagramClient


DATABASE_URL = os.getenv("DATABASE_URL")
BROKER_URL = os.getenv("CELERY_BROKER_URL", "redis://localhost:6379/0")

app = Celery('scheduled_posts', broker=BROKER_URL)


def _get_connection():
    """スケジュール用データベースへの接続を返す。"""
    if not DATABASE_URL:
        raise EnvironmentError("DATABASE_URL must be set")
    return psycopg2.connect(DATABASE_URL)


def add_scheduled_post(image_path: str, caption: str, post_time: datetime, media_type: str) -> None:
    """scheduled_posts テーブルに新しい投稿を追加する。"""
    with _get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO scheduled_posts (image_path, caption, post_time, type, status)
                VALUES (%s, %s, %s, %s, 'pending')
                """,
                (image_path, caption, post_time, media_type),
            )
        conn.commit()


@app.task
def process_scheduled_posts():
    """予約済み投稿をInstagramへアップロードするタスク。"""
    with _get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT id, image_path, caption, post_time, type
                FROM scheduled_posts
                WHERE status != 'posted' AND post_time <= NOW()
                ORDER BY post_time ASC
                """
            )
            rows = cur.fetchall()

            if not rows:
                return

            client = InstagramClient()
            client.login()

            for row in rows:
                post_id, path, caption, post_time, media_type = row
                response = client.upload_content(path, caption, media_type)
                cur.execute(
                    "UPDATE scheduled_posts SET status='posted', response=%s WHERE id=%s",
                    (response, post_id),
                )
        conn.commit()

