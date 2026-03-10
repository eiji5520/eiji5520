// ============================================================
// XBoard - IPC ハンドラー
// メインプロセスのIPC通信ハンドラーを一括登録
// ============================================================

import { ipcMain, BrowserWindow } from 'electron';
import { IPC_CHANNELS, Account, AccountGroup, BulkOperationRequest, AppSettings } from '../shared/types';
import { DEFAULT_SETTINGS } from '../shared/constants';
import * as db from './database';
import { encrypt, decrypt } from './crypto';
import { createAccountSession, closeSession, checkLoginStatus, exportCookies } from './session-manager';
import { executeBulkOperation } from './bulk-operations';
import { exportAccountsCSV, importAccountsCSV, exportLogsCSV, importFollowListCSV, exportFollowListCSV } from './csv-handler';
import { checkSearchBan, checkReplyBan } from './background-tasks';
import { getSessionWindow } from './session-manager';

function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

export function registerIPCHandlers(mainWindow: BrowserWindow): void {
  // ============================================================
  // アカウント管理
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.ACCOUNT_LIST, () => {
    return db.getAccounts();
  });

  ipcMain.handle(IPC_CHANNELS.ACCOUNT_ADD, (_, accountData: Partial<Account>) => {
    const account: Account = {
      id: generateId(),
      username: accountData.username || '',
      displayName: accountData.displayName || accountData.username || '',
      status: 'logged_out',
      groupIds: accountData.groupIds || [],
      proxy: accountData.proxy,
      authToken: accountData.authToken ? encrypt(accountData.authToken) : undefined,
      notes: accountData.notes,
      createdAt: Date.now(),
      updatedAt: Date.now(),
    };
    db.addAccount(account);
    return account;
  });

  ipcMain.handle(IPC_CHANNELS.ACCOUNT_UPDATE, (_, accountData: Partial<Account> & { id: string }) => {
    // 機微情報は暗号化してから保存
    if (accountData.authToken && !accountData.authToken.includes(':')) {
      accountData.authToken = encrypt(accountData.authToken);
    }
    db.updateAccount(accountData);
    return db.getAccountById(accountData.id);
  });

  ipcMain.handle(IPC_CHANNELS.ACCOUNT_DELETE, (_, id: string) => {
    closeSession(id);
    db.deleteAccount(id);
    return { success: true };
  });

  ipcMain.handle(IPC_CHANNELS.ACCOUNT_IMPORT_CSV, () => {
    return importAccountsCSV(mainWindow);
  });

  ipcMain.handle(IPC_CHANNELS.ACCOUNT_EXPORT_CSV, () => {
    return exportAccountsCSV(mainWindow);
  });

  // ============================================================
  // グループ管理
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.GROUP_LIST, () => {
    return db.getGroups();
  });

  ipcMain.handle(IPC_CHANNELS.GROUP_ADD, (_, groupData: Partial<AccountGroup>) => {
    const group: AccountGroup = {
      id: generateId(),
      name: groupData.name || '新規グループ',
      color: groupData.color || '#4ECDC4',
      description: groupData.description,
      createdAt: Date.now(),
    };
    db.addGroup(group);
    return group;
  });

  ipcMain.handle(IPC_CHANNELS.GROUP_UPDATE, (_, groupData: Partial<AccountGroup> & { id: string }) => {
    db.updateGroup(groupData);
    return { success: true };
  });

  ipcMain.handle(IPC_CHANNELS.GROUP_DELETE, (_, id: string) => {
    db.deleteGroup(id);
    return { success: true };
  });

  // ============================================================
  // セッション管理
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.SESSION_OPEN, (_, accountId: string) => {
    const account = db.getAccountById(accountId);
    if (!account) throw new Error('アカウントが見つかりません');
    createAccountSession(account, mainWindow);
    return { success: true };
  });

  ipcMain.handle(IPC_CHANNELS.SESSION_CLOSE, (_, accountId: string) => {
    closeSession(accountId);
    return { success: true };
  });

  ipcMain.handle(IPC_CHANNELS.SESSION_LOGIN, async (_, accountId: string) => {
    const account = db.getAccountById(accountId);
    if (!account) throw new Error('アカウントが見つかりません');
    const win = createAccountSession(account, mainWindow);

    // ログイン完了後にCookieを保存するリスナー
    win.webContents.on('did-navigate', async (_, url) => {
      if (url === 'https://x.com/home' || url === 'https://x.com/') {
        const cookies = await exportCookies(accountId);
        if (cookies) {
          db.updateAccount({
            id: accountId,
            status: 'active',
            cookies: encrypt(cookies),
            lastChecked: Date.now(),
          });
          mainWindow.webContents.send('background-event', {
            type: 'status_change',
            accountId,
            message: `@${account.username} ログイン成功`,
          });
        }
      }
    });

    return { success: true };
  });

  ipcMain.handle(IPC_CHANNELS.SESSION_CHECK_STATUS, async (_, accountId: string) => {
    const status = await checkLoginStatus(accountId);
    db.updateAccount({ id: accountId, status, lastChecked: Date.now() });
    return status;
  });

  // ============================================================
  // 一括操作
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.BULK_EXECUTE, async (_, request: BulkOperationRequest) => {
    const result = await executeBulkOperation(request, (progress) => {
      mainWindow.webContents.send('bulk-progress', progress);
    });
    return result;
  });

  // ============================================================
  // 通知・DM
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.NOTIFICATION_FETCH, (_, accountId?: string) => {
    return db.getNotifications(accountId);
  });

  // ============================================================
  // バンチェック
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.BAN_CHECK, async (_, accountId: string) => {
    const account = db.getAccountById(accountId);
    if (!account) throw new Error('アカウントが見つかりません');

    // バンチェック用に別アカウントのセッションを使用（自分のセッションでは正確にチェックできない）
    // まずは同アカウントで簡易チェック
    const win = getSessionWindow(accountId);
    if (!win) throw new Error('セッションが開かれていません');

    const searchBan = await checkSearchBan(win, account.username);
    const replyBan = await checkReplyBan(win, account.username);

    const result = {
      accountId,
      username: account.username,
      searchBan,
      replyBan,
      ghostBan: false,
      checkedAt: Date.now(),
    };

    db.addLog({
      id: generateId(),
      accountId,
      type: 'ban_check',
      status: 'success',
      message: `サーチバン: ${searchBan ? 'あり' : 'なし'}, リプライバン: ${replyBan ? 'あり' : 'なし'}`,
      timestamp: Date.now(),
    });

    return result;
  });

  // ============================================================
  // 設定
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.SETTINGS_GET, () => {
    const stored = db.getSetting('app_settings');
    if (stored) {
      return { ...DEFAULT_SETTINGS, ...JSON.parse(stored) };
    }
    return DEFAULT_SETTINGS;
  });

  ipcMain.handle(IPC_CHANNELS.SETTINGS_UPDATE, (_, settings: Partial<AppSettings>) => {
    const current = db.getSetting('app_settings');
    const merged = { ...(current ? JSON.parse(current) : DEFAULT_SETTINGS), ...settings };
    db.setSetting('app_settings', JSON.stringify(merged));
    return merged;
  });

  // ============================================================
  // ログ
  // ============================================================

  ipcMain.handle(IPC_CHANNELS.LOG_LIST, (_, limit?: number, accountId?: string) => {
    return db.getLogs(limit || 100, 0, accountId);
  });

  ipcMain.handle(IPC_CHANNELS.LOG_EXPORT, () => {
    return exportLogsCSV(mainWindow);
  });
}
