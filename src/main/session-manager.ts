// ============================================================
// XBoard - ブラウザセッション管理
// 各アカウントに独立したBrowserWindowを割り当て、
// プロキシ設定・Cookie分離を実現する
// ============================================================

import { BrowserWindow, session, Session } from 'electron';
import { Account, ProxyConfig } from '../shared/types';
import { X_BASE_URL, X_LOGIN_URL, DEFAULT_USER_AGENT } from '../shared/constants';
import { decrypt } from './crypto';

interface ManagedSession {
  window: BrowserWindow;
  session: Session;
  accountId: string;
}

const sessions = new Map<string, ManagedSession>();

/** アカウント用の分離セッションを作成 */
export function createAccountSession(account: Account, parentWindow: BrowserWindow): BrowserWindow {
  // 既存セッションがあれば返す
  const existing = sessions.get(account.id);
  if (existing && !existing.window.isDestroyed()) {
    existing.window.focus();
    return existing.window;
  }

  // パーティション名でセッションを分離
  const partition = `persist:account-${account.id}`;
  const ses = session.fromPartition(partition);

  // プロキシ設定
  if (account.proxy?.enabled) {
    applyProxy(ses, account.proxy);
  }

  // User-Agent 設定
  ses.setUserAgent(DEFAULT_USER_AGENT);

  // BrowserWindow 作成
  const win = new BrowserWindow({
    width: 500,
    height: 700,
    parent: parentWindow,
    title: `@${account.username} - XBoard`,
    webPreferences: {
      session: ses,
      sandbox: true,
      contextIsolation: true,
      nodeIntegration: false,
    },
    show: false,
  });

  // 保存済みCookieを復元
  if (account.cookies) {
    restoreCookies(ses, account.cookies).then(() => {
      win.loadURL(X_BASE_URL);
    });
  } else {
    win.loadURL(X_LOGIN_URL);
  }

  win.once('ready-to-show', () => win.show());

  win.on('closed', () => {
    sessions.delete(account.id);
  });

  sessions.set(account.id, { window: win, session: ses, accountId: account.id });
  return win;
}

/** プロキシを設定 */
function applyProxy(ses: Session, proxy: ProxyConfig): void {
  const proxyUrl = proxy.username
    ? `${proxy.protocol}://${proxy.username}:${proxy.password}@${proxy.host}:${proxy.port}`
    : `${proxy.protocol}://${proxy.host}:${proxy.port}`;

  ses.setProxy({ proxyRules: proxyUrl }).catch(err => {
    console.error('プロキシ設定エラー:', err);
  });
}

/** 保存済みCookieを復元 */
async function restoreCookies(ses: Session, encryptedCookies: string): Promise<void> {
  try {
    const cookieJson = decrypt(encryptedCookies);
    const cookies: Electron.Cookie[] = JSON.parse(cookieJson);

    for (const cookie of cookies) {
      const url = `https://${cookie.domain?.replace(/^\./, '')}${cookie.path || '/'}`;
      await ses.cookies.set({
        url,
        name: cookie.name,
        value: cookie.value,
        domain: cookie.domain,
        path: cookie.path,
        secure: cookie.secure,
        httpOnly: cookie.httpOnly,
        sameSite: cookie.sameSite as any,
      });
    }
  } catch (err) {
    console.error('Cookie復元エラー:', err);
  }
}

/** セッションからCookieを取得（暗号化前のJSON文字列） */
export async function exportCookies(accountId: string): Promise<string | null> {
  const managed = sessions.get(accountId);
  if (!managed) return null;

  const cookies = await managed.session.cookies.get({ domain: '.x.com' });
  return JSON.stringify(cookies);
}

/** ログイン状態をチェック */
export async function checkLoginStatus(accountId: string): Promise<'active' | 'locked' | 'suspended' | 'logged_out'> {
  const managed = sessions.get(accountId);
  if (!managed || managed.window.isDestroyed()) return 'logged_out';

  try {
    const cookies = await managed.session.cookies.get({ domain: '.x.com', name: 'auth_token' });
    if (cookies.length === 0) return 'logged_out';

    // ページタイトルやURLからロック・凍結を検出
    const url = managed.window.webContents.getURL();
    if (url.includes('/account/access')) return 'locked';
    if (url.includes('/suspended')) return 'suspended';

    return 'active';
  } catch {
    return 'unknown' as any;
  }
}

/** セッションを閉じる */
export function closeSession(accountId: string): void {
  const managed = sessions.get(accountId);
  if (managed && !managed.window.isDestroyed()) {
    managed.window.close();
  }
  sessions.delete(accountId);
}

/** 全セッションを閉じる */
export function closeAllSessions(): void {
  for (const [id] of sessions) {
    closeSession(id);
  }
}

/** 特定アカウントのセッションウィンドウを取得 */
export function getSessionWindow(accountId: string): BrowserWindow | null {
  const managed = sessions.get(accountId);
  if (managed && !managed.window.isDestroyed()) return managed.window;
  return null;
}

/** アクティブなセッション数を取得 */
export function getActiveSessionCount(): number {
  return sessions.size;
}
