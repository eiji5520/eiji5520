// ============================================================
// XBoard - 共通型定義
// ============================================================

/** アカウントのログイン状態 */
export type AccountStatus = 'active' | 'locked' | 'suspended' | 'logged_out' | 'unknown';

/** プロキシ設定 */
export interface ProxyConfig {
  enabled: boolean;
  host: string;
  port: number;
  protocol: 'http' | 'https' | 'socks4' | 'socks5';
  username?: string;
  password?: string;
}

/** アカウント情報 */
export interface Account {
  id: string;
  username: string;
  displayName: string;
  avatarUrl?: string;
  status: AccountStatus;
  groupIds: string[];
  proxy?: ProxyConfig;
  cookies?: string; // 暗号化済みCookie JSON
  authToken?: string; // 暗号化済み認証トークン
  lastChecked?: number; // Unix timestamp
  createdAt: number;
  updatedAt: number;
  notes?: string;
}

/** アカウントグループ */
export interface AccountGroup {
  id: string;
  name: string;
  color: string;
  description?: string;
  createdAt: number;
}

/** 操作ログ */
export interface OperationLog {
  id: string;
  accountId: string;
  type: OperationType;
  target?: string; // 対象URL or ユーザー名
  status: 'success' | 'failure' | 'pending';
  message?: string;
  timestamp: number;
}

export type OperationType =
  | 'login'
  | 'post'
  | 'like'
  | 'repost'
  | 'bookmark'
  | 'follow'
  | 'unfollow'
  | 'ban_check'
  | 'notification_fetch'
  | 'dm_fetch'
  | 'data_fetch';

/** ポストデータ */
export interface PostData {
  id: string;
  text: string;
  authorUsername: string;
  timestamp: number;
  likeCount: number;
  repostCount: number;
  replyCount: number;
  impressionCount: number;
  mediaUrls?: string[];
}

/** 通知データ */
export interface NotificationData {
  id: string;
  accountId: string;
  type: 'like' | 'repost' | 'reply' | 'follow' | 'mention' | 'dm';
  fromUsername: string;
  content?: string;
  postId?: string;
  timestamp: number;
  read: boolean;
}

/** DM メッセージ */
export interface DMMessage {
  id: string;
  accountId: string;
  conversationId: string;
  fromUsername: string;
  toUsername: string;
  text: string;
  timestamp: number;
  read: boolean;
}

/** バンチェック結果 */
export interface BanCheckResult {
  accountId: string;
  username: string;
  searchBan: boolean;
  replyBan: boolean;
  ghostBan: boolean;
  checkedAt: number;
  details?: string;
}

/** 一括操作リクエスト */
export interface BulkOperationRequest {
  type: 'post' | 'like' | 'repost' | 'bookmark' | 'follow';
  accountIds: string[];
  content?: string; // post用
  targetUrl?: string; // like/repost/bookmark用
  targetUsername?: string; // follow用
  delayMs?: number; // 操作間の遅延(ms)
}

/** CSV インポート/エクスポート設定 */
export interface CSVConfig {
  delimiter: string;
  encoding: 'utf-8' | 'shift-jis';
  hasHeader: boolean;
}

/** アプリ設定 */
export interface AppSettings {
  theme: 'dark' | 'light';
  language: 'ja' | 'en';
  checkInterval: number; // ログイン状態チェック間隔(分)
  notificationInterval: number; // 通知チェック間隔(分)
  defaultDelay: number; // デフォルト操作遅延(ms)
  encryptionEnabled: boolean;
  syncEnabled: boolean;
  syncPath?: string;
}

/** IPC チャンネル名 */
export const IPC_CHANNELS = {
  // アカウント
  ACCOUNT_LIST: 'account:list',
  ACCOUNT_ADD: 'account:add',
  ACCOUNT_UPDATE: 'account:update',
  ACCOUNT_DELETE: 'account:delete',
  ACCOUNT_IMPORT_CSV: 'account:import-csv',
  ACCOUNT_EXPORT_CSV: 'account:export-csv',

  // グループ
  GROUP_LIST: 'group:list',
  GROUP_ADD: 'group:add',
  GROUP_UPDATE: 'group:update',
  GROUP_DELETE: 'group:delete',

  // ブラウザセッション
  SESSION_OPEN: 'session:open',
  SESSION_CLOSE: 'session:close',
  SESSION_LOGIN: 'session:login',
  SESSION_CHECK_STATUS: 'session:check-status',

  // 一括操作
  BULK_EXECUTE: 'bulk:execute',
  BULK_STATUS: 'bulk:status',

  // 通知・DM
  NOTIFICATION_FETCH: 'notification:fetch',
  DM_FETCH: 'dm:fetch',
  DM_SEND: 'dm:send',

  // バンチェック
  BAN_CHECK: 'ban:check',

  // データ取得
  DATA_FETCH_POSTS: 'data:fetch-posts',
  DATA_DOWNLOAD_MEDIA: 'data:download-media',

  // 設定
  SETTINGS_GET: 'settings:get',
  SETTINGS_UPDATE: 'settings:update',

  // ログ
  LOG_LIST: 'log:list',
  LOG_EXPORT: 'log:export',

  // 暗号化
  CRYPTO_ENCRYPT: 'crypto:encrypt',
  CRYPTO_DECRYPT: 'crypto:decrypt',
} as const;
