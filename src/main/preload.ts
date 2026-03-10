// ============================================================
// XBoard - Preload スクリプト
// renderer <-> main プロセス間の安全なブリッジ
// ============================================================

import { contextBridge, ipcRenderer } from 'electron';
import { IPC_CHANNELS } from '../shared/types';

const api = {
  // アカウント
  getAccounts: () => ipcRenderer.invoke(IPC_CHANNELS.ACCOUNT_LIST),
  addAccount: (account: any) => ipcRenderer.invoke(IPC_CHANNELS.ACCOUNT_ADD, account),
  updateAccount: (account: any) => ipcRenderer.invoke(IPC_CHANNELS.ACCOUNT_UPDATE, account),
  deleteAccount: (id: string) => ipcRenderer.invoke(IPC_CHANNELS.ACCOUNT_DELETE, id),
  importAccountsCSV: () => ipcRenderer.invoke(IPC_CHANNELS.ACCOUNT_IMPORT_CSV),
  exportAccountsCSV: () => ipcRenderer.invoke(IPC_CHANNELS.ACCOUNT_EXPORT_CSV),

  // グループ
  getGroups: () => ipcRenderer.invoke(IPC_CHANNELS.GROUP_LIST),
  addGroup: (group: any) => ipcRenderer.invoke(IPC_CHANNELS.GROUP_ADD, group),
  updateGroup: (group: any) => ipcRenderer.invoke(IPC_CHANNELS.GROUP_UPDATE, group),
  deleteGroup: (id: string) => ipcRenderer.invoke(IPC_CHANNELS.GROUP_DELETE, id),

  // セッション
  openSession: (accountId: string) => ipcRenderer.invoke(IPC_CHANNELS.SESSION_OPEN, accountId),
  closeSession: (accountId: string) => ipcRenderer.invoke(IPC_CHANNELS.SESSION_CLOSE, accountId),
  loginAccount: (accountId: string) => ipcRenderer.invoke(IPC_CHANNELS.SESSION_LOGIN, accountId),
  checkStatus: (accountId: string) => ipcRenderer.invoke(IPC_CHANNELS.SESSION_CHECK_STATUS, accountId),

  // 一括操作
  executeBulk: (request: any) => ipcRenderer.invoke(IPC_CHANNELS.BULK_EXECUTE, request),

  // 通知・DM
  fetchNotifications: (accountId?: string) => ipcRenderer.invoke(IPC_CHANNELS.NOTIFICATION_FETCH, accountId),
  fetchDMs: (accountId: string) => ipcRenderer.invoke(IPC_CHANNELS.DM_FETCH, accountId),

  // バンチェック
  checkBan: (accountId: string) => ipcRenderer.invoke(IPC_CHANNELS.BAN_CHECK, accountId),

  // データ取得
  fetchPosts: (accountId: string, targetUsername: string) => ipcRenderer.invoke(IPC_CHANNELS.DATA_FETCH_POSTS, accountId, targetUsername),
  downloadMedia: (url: string, type: string) => ipcRenderer.invoke(IPC_CHANNELS.DATA_DOWNLOAD_MEDIA, url, type),

  // 設定
  getSettings: () => ipcRenderer.invoke(IPC_CHANNELS.SETTINGS_GET),
  updateSettings: (settings: any) => ipcRenderer.invoke(IPC_CHANNELS.SETTINGS_UPDATE, settings),

  // ログ
  getLogs: (limit?: number, accountId?: string) => ipcRenderer.invoke(IPC_CHANNELS.LOG_LIST, limit, accountId),
  exportLogs: () => ipcRenderer.invoke(IPC_CHANNELS.LOG_EXPORT),

  // イベントリスナー
  onBackgroundEvent: (callback: (event: any) => void) => {
    const handler = (_: any, event: any) => callback(event);
    ipcRenderer.on('background-event', handler);
    return () => ipcRenderer.removeListener('background-event', handler);
  },

  onBulkProgress: (callback: (progress: any) => void) => {
    const handler = (_: any, progress: any) => callback(progress);
    ipcRenderer.on('bulk-progress', handler);
    return () => ipcRenderer.removeListener('bulk-progress', handler);
  },
};

contextBridge.exposeInMainWorld('xboard', api);

// TypeScript型定義
export type XBoardAPI = typeof api;
