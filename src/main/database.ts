// ============================================================
// XBoard - SQLite データベースモジュール
// アカウント、グループ、ログなどのローカル永続化
// ============================================================

import Database from 'better-sqlite3';
import * as path from 'path';
import { app } from 'electron';
import { DB_FILE_NAME } from '../shared/constants';
import { Account, AccountGroup, OperationLog, NotificationData, AppSettings } from '../shared/types';

let db: Database.Database;

/** DB初期化 */
export function initDatabase(): void {
  const dbPath = path.join(app.getPath('userData'), DB_FILE_NAME);
  db = new Database(dbPath);

  // WALモードで高速化
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');

  createTables();
}

function createTables(): void {
  db.exec(`
    CREATE TABLE IF NOT EXISTS accounts (
      id TEXT PRIMARY KEY,
      username TEXT NOT NULL,
      display_name TEXT NOT NULL DEFAULT '',
      avatar_url TEXT,
      status TEXT NOT NULL DEFAULT 'logged_out',
      group_ids TEXT NOT NULL DEFAULT '[]',
      proxy_config TEXT,
      cookies TEXT,
      auth_token TEXT,
      last_checked INTEGER,
      created_at INTEGER NOT NULL,
      updated_at INTEGER NOT NULL,
      notes TEXT
    );

    CREATE TABLE IF NOT EXISTS groups (
      id TEXT PRIMARY KEY,
      name TEXT NOT NULL,
      color TEXT NOT NULL DEFAULT '#4ECDC4',
      description TEXT,
      created_at INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS operation_logs (
      id TEXT PRIMARY KEY,
      account_id TEXT NOT NULL,
      type TEXT NOT NULL,
      target TEXT,
      status TEXT NOT NULL DEFAULT 'pending',
      message TEXT,
      timestamp INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS notifications (
      id TEXT PRIMARY KEY,
      account_id TEXT NOT NULL,
      type TEXT NOT NULL,
      from_username TEXT NOT NULL,
      content TEXT,
      post_id TEXT,
      timestamp INTEGER NOT NULL,
      read INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS dm_messages (
      id TEXT PRIMARY KEY,
      account_id TEXT NOT NULL,
      conversation_id TEXT NOT NULL,
      from_username TEXT NOT NULL,
      to_username TEXT NOT NULL,
      text TEXT NOT NULL,
      timestamp INTEGER NOT NULL,
      read INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS settings (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_logs_account ON operation_logs(account_id);
    CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON operation_logs(timestamp);
    CREATE INDEX IF NOT EXISTS idx_notifications_account ON notifications(account_id);
    CREATE INDEX IF NOT EXISTS idx_dm_conversation ON dm_messages(conversation_id);
  `);
}

// ============================================================
// アカウント CRUD
// ============================================================

export function getAccounts(): Account[] {
  const rows = db.prepare('SELECT * FROM accounts ORDER BY created_at DESC').all() as any[];
  return rows.map(rowToAccount);
}

export function getAccountById(id: string): Account | undefined {
  const row = db.prepare('SELECT * FROM accounts WHERE id = ?').get(id) as any;
  return row ? rowToAccount(row) : undefined;
}

export function addAccount(account: Account): void {
  db.prepare(`
    INSERT INTO accounts (id, username, display_name, avatar_url, status, group_ids, proxy_config, cookies, auth_token, last_checked, created_at, updated_at, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `).run(
    account.id,
    account.username,
    account.displayName,
    account.avatarUrl || null,
    account.status,
    JSON.stringify(account.groupIds),
    account.proxy ? JSON.stringify(account.proxy) : null,
    account.cookies || null,
    account.authToken || null,
    account.lastChecked || null,
    account.createdAt,
    account.updatedAt,
    account.notes || null,
  );
}

export function updateAccount(account: Partial<Account> & { id: string }): void {
  const existing = getAccountById(account.id);
  if (!existing) throw new Error(`アカウント ${account.id} が見つかりません`);

  const merged = { ...existing, ...account, updatedAt: Date.now() };
  db.prepare(`
    UPDATE accounts SET
      username = ?, display_name = ?, avatar_url = ?, status = ?,
      group_ids = ?, proxy_config = ?, cookies = ?, auth_token = ?,
      last_checked = ?, updated_at = ?, notes = ?
    WHERE id = ?
  `).run(
    merged.username,
    merged.displayName,
    merged.avatarUrl || null,
    merged.status,
    JSON.stringify(merged.groupIds),
    merged.proxy ? JSON.stringify(merged.proxy) : null,
    merged.cookies || null,
    merged.authToken || null,
    merged.lastChecked || null,
    merged.updatedAt,
    merged.notes || null,
    merged.id,
  );
}

export function deleteAccount(id: string): void {
  db.prepare('DELETE FROM accounts WHERE id = ?').run(id);
  db.prepare('DELETE FROM operation_logs WHERE account_id = ?').run(id);
  db.prepare('DELETE FROM notifications WHERE account_id = ?').run(id);
  db.prepare('DELETE FROM dm_messages WHERE account_id = ?').run(id);
}

function rowToAccount(row: any): Account {
  return {
    id: row.id,
    username: row.username,
    displayName: row.display_name,
    avatarUrl: row.avatar_url || undefined,
    status: row.status,
    groupIds: JSON.parse(row.group_ids || '[]'),
    proxy: row.proxy_config ? JSON.parse(row.proxy_config) : undefined,
    cookies: row.cookies || undefined,
    authToken: row.auth_token || undefined,
    lastChecked: row.last_checked || undefined,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
    notes: row.notes || undefined,
  };
}

// ============================================================
// グループ CRUD
// ============================================================

export function getGroups(): AccountGroup[] {
  return db.prepare('SELECT * FROM groups ORDER BY name').all() as AccountGroup[];
}

export function addGroup(group: AccountGroup): void {
  db.prepare('INSERT INTO groups (id, name, color, description, created_at) VALUES (?, ?, ?, ?, ?)')
    .run(group.id, group.name, group.color, group.description || null, group.createdAt);
}

export function updateGroup(group: Partial<AccountGroup> & { id: string }): void {
  const existing = db.prepare('SELECT * FROM groups WHERE id = ?').get(group.id) as any;
  if (!existing) throw new Error(`グループ ${group.id} が見つかりません`);

  db.prepare('UPDATE groups SET name = ?, color = ?, description = ? WHERE id = ?')
    .run(
      group.name ?? existing.name,
      group.color ?? existing.color,
      group.description ?? existing.description,
      group.id,
    );
}

export function deleteGroup(id: string): void {
  db.prepare('DELETE FROM groups WHERE id = ?').run(id);
}

// ============================================================
// 操作ログ
// ============================================================

export function addLog(log: OperationLog): void {
  db.prepare('INSERT INTO operation_logs (id, account_id, type, target, status, message, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?)')
    .run(log.id, log.accountId, log.type, log.target || null, log.status, log.message || null, log.timestamp);
}

export function getLogs(limit: number = 100, offset: number = 0, accountId?: string): OperationLog[] {
  if (accountId) {
    return db.prepare('SELECT * FROM operation_logs WHERE account_id = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?')
      .all(accountId, limit, offset) as OperationLog[];
  }
  return db.prepare('SELECT * FROM operation_logs ORDER BY timestamp DESC LIMIT ? OFFSET ?')
    .all(limit, offset) as OperationLog[];
}

// ============================================================
// 通知
// ============================================================

export function addNotification(notification: NotificationData): void {
  db.prepare(`
    INSERT OR IGNORE INTO notifications (id, account_id, type, from_username, content, post_id, timestamp, read)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  `).run(
    notification.id, notification.accountId, notification.type,
    notification.fromUsername, notification.content || null,
    notification.postId || null, notification.timestamp, notification.read ? 1 : 0,
  );
}

export function getNotifications(accountId?: string, limit: number = 50): NotificationData[] {
  const query = accountId
    ? 'SELECT * FROM notifications WHERE account_id = ? ORDER BY timestamp DESC LIMIT ?'
    : 'SELECT * FROM notifications ORDER BY timestamp DESC LIMIT ?';
  const rows = accountId
    ? db.prepare(query).all(accountId, limit) as any[]
    : db.prepare(query).all(limit) as any[];
  return rows.map(r => ({ ...r, read: !!r.read }));
}

// ============================================================
// 設定
// ============================================================

export function getSetting(key: string): string | undefined {
  const row = db.prepare('SELECT value FROM settings WHERE key = ?').get(key) as any;
  return row?.value;
}

export function setSetting(key: string, value: string): void {
  db.prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)').run(key, value);
}

/** DB接続を閉じる */
export function closeDatabase(): void {
  if (db) db.close();
}
