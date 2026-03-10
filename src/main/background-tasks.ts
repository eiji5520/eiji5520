// ============================================================
// XBoard - バックグラウンド監視タスク
// ログイン状態チェック、ロック/凍結検出、通知取得
// ============================================================

import { BrowserWindow } from 'electron';
import { getAccounts, updateAccount, addNotification, addLog } from './database';
import { checkLoginStatus, getSessionWindow } from './session-manager';
import { Account, NotificationData, OperationLog } from '../shared/types';

function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

type TaskCallback = (event: BackgroundEvent) => void;

export interface BackgroundEvent {
  type: 'status_change' | 'notification' | 'lock_detected' | 'suspend_detected' | 'error';
  accountId: string;
  data?: any;
  message: string;
}

let statusCheckInterval: ReturnType<typeof setInterval> | null = null;
let notificationCheckInterval: ReturnType<typeof setInterval> | null = null;
let eventCallback: TaskCallback | null = null;

/** イベントコールバックを設定 */
export function setEventCallback(callback: TaskCallback): void {
  eventCallback = callback;
}

function emitEvent(event: BackgroundEvent): void {
  eventCallback?.(event);
}

// ============================================================
// ログイン状態チェック
// ============================================================

/** ログイン状態の定期チェックを開始 */
export function startStatusCheck(intervalMinutes: number = 30): void {
  if (statusCheckInterval) clearInterval(statusCheckInterval);

  statusCheckInterval = setInterval(async () => {
    await checkAllAccountStatuses();
  }, intervalMinutes * 60 * 1000);

  // 起動時に一度チェック
  setTimeout(() => checkAllAccountStatuses(), 5000);
}

async function checkAllAccountStatuses(): Promise<void> {
  const accounts = getAccounts();

  for (const account of accounts) {
    const win = getSessionWindow(account.id);
    if (!win) continue;

    try {
      const newStatus = await checkLoginStatus(account.id);
      const oldStatus = account.status;

      if (newStatus !== oldStatus) {
        updateAccount({ id: account.id, status: newStatus, lastChecked: Date.now() });

        if (newStatus === 'locked') {
          emitEvent({
            type: 'lock_detected',
            accountId: account.id,
            message: `@${account.username} がロックされました`,
          });
        } else if (newStatus === 'suspended') {
          emitEvent({
            type: 'suspend_detected',
            accountId: account.id,
            message: `@${account.username} が凍結されました`,
          });
        }

        emitEvent({
          type: 'status_change',
          accountId: account.id,
          data: { oldStatus, newStatus },
          message: `@${account.username} のステータスが ${oldStatus} → ${newStatus} に変化`,
        });

        addLog({
          id: generateId(),
          accountId: account.id,
          type: 'login',
          status: newStatus === 'active' ? 'success' : 'failure',
          message: `ステータス変化: ${oldStatus} → ${newStatus}`,
          timestamp: Date.now(),
        });
      } else {
        updateAccount({ id: account.id, lastChecked: Date.now() });
      }
    } catch (err: any) {
      emitEvent({
        type: 'error',
        accountId: account.id,
        message: `ステータスチェックエラー: ${err.message}`,
      });
    }
  }
}

// ============================================================
// 通知チェック
// ============================================================

/** 通知の定期チェックを開始 */
export function startNotificationCheck(intervalMinutes: number = 5): void {
  if (notificationCheckInterval) clearInterval(notificationCheckInterval);

  notificationCheckInterval = setInterval(async () => {
    await checkAllNotifications();
  }, intervalMinutes * 60 * 1000);
}

async function checkAllNotifications(): Promise<void> {
  const accounts = getAccounts().filter(a => a.status === 'active');

  for (const account of accounts) {
    const win = getSessionWindow(account.id);
    if (!win) continue;

    try {
      const notifications = await fetchNotificationsFromPage(win, account.id);
      for (const notif of notifications) {
        addNotification(notif);
        emitEvent({
          type: 'notification',
          accountId: account.id,
          data: notif,
          message: `@${account.username}: 新着通知 (${notif.type})`,
        });
      }
    } catch (err: any) {
      emitEvent({
        type: 'error',
        accountId: account.id,
        message: `通知取得エラー: ${err.message}`,
      });
    }
  }
}

/** ページから通知を取得するスクリプト */
async function fetchNotificationsFromPage(win: BrowserWindow, accountId: string): Promise<NotificationData[]> {
  const script = `
    (async () => {
      try {
        // 通知ページに遷移して情報取得
        const currentUrl = window.location.href;
        const notifBadge = document.querySelector('[data-testid="AppTabBar_Notifications_Link"] [aria-live]');
        const count = notifBadge ? parseInt(notifBadge.textContent || '0') : 0;

        if (count === 0) return [];

        // 通知カウントのみ返す(ページ遷移はしない)
        return [{ hasNew: true, count }];
      } catch (err) {
        return [];
      }
    })();
  `;

  const result = await win.webContents.executeJavaScript(script);
  if (!result || result.length === 0) return [];

  // 簡易通知データ作成
  return result
    .filter((r: any) => r.hasNew)
    .map((r: any) => ({
      id: generateId(),
      accountId,
      type: 'mention' as const,
      fromUsername: 'unknown',
      content: `${r.count}件の新着通知`,
      timestamp: Date.now(),
      read: false,
    }));
}

// ============================================================
// バンチェック
// ============================================================

/** サーチバンチェック */
export async function checkSearchBan(win: BrowserWindow, username: string): Promise<boolean> {
  const script = `
    (async () => {
      try {
        window.location.href = 'https://x.com/search?q=from%3A${username}&src=typed_query';
        await new Promise(r => setTimeout(r, 3000));

        const noResults = document.querySelector('[data-testid="empty_state_header_text"]');
        const tweets = document.querySelectorAll('[data-testid="tweet"]');

        return { banned: !!noResults && tweets.length === 0 };
      } catch (err) {
        return { banned: false, error: err.message };
      }
    })();
  `;

  const result = await win.webContents.executeJavaScript(script);
  return result?.banned || false;
}

/** リプライバンチェック */
export async function checkReplyBan(win: BrowserWindow, username: string): Promise<boolean> {
  const script = `
    (async () => {
      try {
        window.location.href = 'https://x.com/${username}/with_replies';
        await new Promise(r => setTimeout(r, 3000));

        const replies = document.querySelectorAll('[data-testid="tweet"]');
        if (replies.length === 0) return { banned: true };

        // リプライが表示されているが、他ユーザーのタイムラインに見えない場合
        return { banned: false, replyCount: replies.length };
      } catch (err) {
        return { banned: false, error: err.message };
      }
    })();
  `;

  const result = await win.webContents.executeJavaScript(script);
  return result?.banned || false;
}

// ============================================================
// クリーンアップ
// ============================================================

/** すべてのバックグラウンドタスクを停止 */
export function stopAllTasks(): void {
  if (statusCheckInterval) {
    clearInterval(statusCheckInterval);
    statusCheckInterval = null;
  }
  if (notificationCheckInterval) {
    clearInterval(notificationCheckInterval);
    notificationCheckInterval = null;
  }
}
