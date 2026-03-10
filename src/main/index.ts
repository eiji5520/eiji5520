// ============================================================
// XBoard - メインプロセスエントリーポイント
// Electron アプリのライフサイクル管理
// ============================================================

import { app, BrowserWindow, Menu, nativeTheme } from 'electron';
import * as path from 'path';
import { initDatabase, closeDatabase } from './database';
import { registerIPCHandlers } from './ipc-handlers';
import { closeAllSessions } from './session-manager';
import { startStatusCheck, startNotificationCheck, stopAllTasks, setEventCallback } from './background-tasks';
import { APP_NAME } from '../shared/constants';

let mainWindow: BrowserWindow | null = null;

function createMainWindow(): void {
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1000,
    minHeight: 700,
    title: APP_NAME,
    backgroundColor: '#1a1a2e',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
    show: false,
  });

  // 開発時はdevserver、プロダクションはビルド済みHTML
  if (process.env.NODE_ENV === 'development') {
    mainWindow.loadURL('http://localhost:3000');
    mainWindow.webContents.openDevTools({ mode: 'detach' });
  } else {
    mainWindow.loadFile(path.join(__dirname, '../renderer/index.html'));
  }

  mainWindow.once('ready-to-show', () => {
    mainWindow?.show();
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // メニュー設定
  const menu = Menu.buildFromTemplate([
    {
      label: APP_NAME,
      submenu: [
        { label: 'XBoard について', role: 'about' },
        { type: 'separator' },
        { label: '設定', accelerator: 'CmdOrCtrl+,', click: () => mainWindow?.webContents.send('navigate', '/settings') },
        { type: 'separator' },
        { label: '終了', role: 'quit' },
      ],
    },
    {
      label: '編集',
      submenu: [
        { label: '元に戻す', role: 'undo' },
        { label: 'やり直す', role: 'redo' },
        { type: 'separator' },
        { label: '切り取り', role: 'cut' },
        { label: 'コピー', role: 'copy' },
        { label: '貼り付け', role: 'paste' },
        { label: 'すべて選択', role: 'selectAll' },
      ],
    },
    {
      label: '表示',
      submenu: [
        { label: '再読み込み', role: 'reload' },
        { label: '開発者ツール', role: 'toggleDevTools' },
        { type: 'separator' },
        { label: '拡大', role: 'zoomIn' },
        { label: '縮小', role: 'zoomOut' },
        { label: '等倍', role: 'resetZoom' },
        { type: 'separator' },
        { label: '全画面', role: 'togglefullscreen' },
      ],
    },
  ]);
  Menu.setApplicationMenu(menu);

  // IPCハンドラー登録
  registerIPCHandlers(mainWindow);

  // バックグラウンドタスクのイベントをrendererに転送
  setEventCallback((event) => {
    mainWindow?.webContents.send('background-event', event);
  });
}

// ============================================================
// アプリケーションライフサイクル
// ============================================================

app.whenReady().then(() => {
  // DB初期化
  initDatabase();

  // メインウィンドウ作成
  createMainWindow();

  // バックグラウンドタスク開始
  startStatusCheck(30);
  startNotificationCheck(5);

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createMainWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('before-quit', () => {
  stopAllTasks();
  closeAllSessions();
  closeDatabase();
});

// セキュリティ: 新しいウィンドウの作成を制限
app.on('web-contents-created', (_, contents) => {
  contents.setWindowOpenHandler(({ url }) => {
    // X.com のURLのみ許可
    if (url.startsWith('https://x.com') || url.startsWith('https://twitter.com')) {
      return { action: 'allow' };
    }
    return { action: 'deny' };
  });
});
