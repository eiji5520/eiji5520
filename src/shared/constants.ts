// ============================================================
// XBoard - 定数定義
// ============================================================

export const APP_NAME = 'XBoard';
export const APP_VERSION = '1.0.0';

export const X_BASE_URL = 'https://x.com';
export const X_LOGIN_URL = 'https://x.com/i/flow/login';
export const X_API_BASE = 'https://x.com/i/api';

export const DEFAULT_USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

export const DEFAULT_SETTINGS = {
  theme: 'dark' as const,
  language: 'ja' as const,
  checkInterval: 30,
  notificationInterval: 5,
  defaultDelay: 2000,
  encryptionEnabled: true,
  syncEnabled: false,
};

export const ACCOUNT_STATUS_LABELS: Record<string, string> = {
  active: 'アクティブ',
  locked: 'ロック済み',
  suspended: '凍結済み',
  logged_out: '未ログイン',
  unknown: '不明',
};

export const OPERATION_TYPE_LABELS: Record<string, string> = {
  login: 'ログイン',
  post: 'ポスト',
  like: 'いいね',
  repost: 'リポスト',
  bookmark: 'ブックマーク',
  follow: 'フォロー',
  unfollow: 'フォロー解除',
  ban_check: 'バンチェック',
  notification_fetch: '通知取得',
  dm_fetch: 'DM取得',
  data_fetch: 'データ取得',
};

export const GROUP_COLORS = [
  '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4',
  '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F',
  '#BB8FCE', '#85C1E9', '#F0B27A', '#82E0AA',
];

export const ENCRYPTION_ALGORITHM = 'aes-256-gcm';
export const ENCRYPTION_KEY_LENGTH = 32;
export const ENCRYPTION_IV_LENGTH = 16;
export const ENCRYPTION_TAG_LENGTH = 16;

export const DB_FILE_NAME = 'xboard.db';
export const SETTINGS_FILE_NAME = 'settings.json';
export const LOG_DIR_NAME = 'logs';
