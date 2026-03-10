// ============================================================
// XBoard - CSV インポート/エクスポート
// ============================================================

import { parse, unparse } from 'papaparse';
import * as fs from 'fs';
import { dialog, BrowserWindow } from 'electron';
import { Account, AccountGroup, OperationLog, ProxyConfig } from '../shared/types';
import { getAccounts, addAccount, getGroups, getLogs } from './database';

function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

// ============================================================
// アカウント CSV
// ============================================================

interface AccountCSVRow {
  username: string;
  display_name: string;
  group: string;
  proxy_host: string;
  proxy_port: string;
  proxy_protocol: string;
  proxy_username: string;
  proxy_password: string;
  auth_token: string;
  notes: string;
}

/** アカウント一覧をCSVエクスポート */
export async function exportAccountsCSV(parentWindow: BrowserWindow): Promise<string | null> {
  const result = await dialog.showSaveDialog(parentWindow, {
    title: 'アカウント一覧をエクスポート',
    defaultPath: 'xboard_accounts.csv',
    filters: [{ name: 'CSV', extensions: ['csv'] }],
  });

  if (result.canceled || !result.filePath) return null;

  const accounts = getAccounts();
  const rows = accounts.map(a => ({
    username: a.username,
    display_name: a.displayName,
    group: a.groupIds.join(';'),
    proxy_host: a.proxy?.host || '',
    proxy_port: a.proxy?.port?.toString() || '',
    proxy_protocol: a.proxy?.protocol || '',
    proxy_username: a.proxy?.username || '',
    proxy_password: '', // パスワードはエクスポートしない
    auth_token: '', // トークンもエクスポートしない
    notes: a.notes || '',
  }));

  const csv = unparse(rows, { header: true });
  fs.writeFileSync(result.filePath, '\ufeff' + csv, 'utf-8'); // BOM付きUTF-8
  return result.filePath;
}

/** CSVからアカウントをインポート */
export async function importAccountsCSV(parentWindow: BrowserWindow): Promise<{ imported: number; skipped: number; errors: string[] }> {
  const result = await dialog.showOpenDialog(parentWindow, {
    title: 'アカウント一覧をインポート',
    filters: [{ name: 'CSV', extensions: ['csv'] }],
    properties: ['openFile'],
  });

  if (result.canceled || result.filePaths.length === 0) {
    return { imported: 0, skipped: 0, errors: [] };
  }

  const csvContent = fs.readFileSync(result.filePaths[0], 'utf-8');
  const parsed = parse<AccountCSVRow>(csvContent, {
    header: true,
    skipEmptyLines: true,
    transformHeader: (h) => h.trim().toLowerCase().replace(/\s+/g, '_'),
  });

  if (parsed.errors.length > 0) {
    return {
      imported: 0,
      skipped: 0,
      errors: parsed.errors.map(e => `行 ${e.row}: ${e.message}`),
    };
  }

  const existingAccounts = getAccounts();
  const existingUsernames = new Set(existingAccounts.map(a => a.username.toLowerCase()));

  let imported = 0;
  let skipped = 0;
  const errors: string[] = [];

  for (const row of parsed.data) {
    if (!row.username) {
      errors.push(`ユーザー名が空の行をスキップしました`);
      skipped++;
      continue;
    }

    const username = row.username.replace(/^@/, '').trim();
    if (existingUsernames.has(username.toLowerCase())) {
      skipped++;
      continue;
    }

    let proxy: ProxyConfig | undefined;
    if (row.proxy_host) {
      proxy = {
        enabled: true,
        host: row.proxy_host.trim(),
        port: parseInt(row.proxy_port) || 8080,
        protocol: (row.proxy_protocol as any) || 'http',
        username: row.proxy_username?.trim() || undefined,
        password: row.proxy_password?.trim() || undefined,
      };
    }

    const account: Account = {
      id: generateId(),
      username,
      displayName: row.display_name || username,
      status: 'logged_out',
      groupIds: row.group ? row.group.split(';').filter(Boolean) : [],
      proxy,
      authToken: row.auth_token?.trim() || undefined,
      notes: row.notes?.trim() || undefined,
      createdAt: Date.now(),
      updatedAt: Date.now(),
    };

    try {
      addAccount(account);
      existingUsernames.add(username.toLowerCase());
      imported++;
    } catch (err: any) {
      errors.push(`@${username}: ${err.message}`);
    }
  }

  return { imported, skipped, errors };
}

// ============================================================
// 操作ログ CSV
// ============================================================

export async function exportLogsCSV(parentWindow: BrowserWindow): Promise<string | null> {
  const result = await dialog.showSaveDialog(parentWindow, {
    title: '操作ログをエクスポート',
    defaultPath: 'xboard_logs.csv',
    filters: [{ name: 'CSV', extensions: ['csv'] }],
  });

  if (result.canceled || !result.filePath) return null;

  const logs = getLogs(10000);
  const rows = logs.map(l => ({
    id: l.id,
    account_id: l.accountId,
    type: l.type,
    target: l.target || '',
    status: l.status,
    message: l.message || '',
    timestamp: new Date(l.timestamp).toISOString(),
  }));

  const csv = unparse(rows, { header: true });
  fs.writeFileSync(result.filePath, '\ufeff' + csv, 'utf-8');
  return result.filePath;
}

// ============================================================
// フォロー対象リスト CSV
// ============================================================

export async function importFollowListCSV(parentWindow: BrowserWindow): Promise<string[]> {
  const result = await dialog.showOpenDialog(parentWindow, {
    title: 'フォロー対象リストをインポート',
    filters: [{ name: 'CSV', extensions: ['csv'] }],
    properties: ['openFile'],
  });

  if (result.canceled || result.filePaths.length === 0) return [];

  const csvContent = fs.readFileSync(result.filePaths[0], 'utf-8');
  const parsed = parse<{ username: string }>(csvContent, {
    header: true,
    skipEmptyLines: true,
  });

  return parsed.data
    .map(row => row.username?.replace(/^@/, '').trim())
    .filter(Boolean);
}

export async function exportFollowListCSV(parentWindow: BrowserWindow, usernames: string[]): Promise<string | null> {
  const result = await dialog.showSaveDialog(parentWindow, {
    title: 'フォロー対象リストをエクスポート',
    defaultPath: 'follow_list.csv',
    filters: [{ name: 'CSV', extensions: ['csv'] }],
  });

  if (result.canceled || !result.filePath) return null;

  const csv = unparse(usernames.map(u => ({ username: u })), { header: true });
  fs.writeFileSync(result.filePath, '\ufeff' + csv, 'utf-8');
  return result.filePath;
}
