const { app, BrowserWindow, ipcMain } = require('electron');
const { autoUpdater } = require('electron-updater');
const path = require('path');
const { spawn } = require('child_process');

let pythonProcess = null;

function setupPython() {
  require('./scripts/setupPython');
}

function createWindow() {
  const win = new BrowserWindow({
    width: 800,
    height: 600,
    webPreferences: {
      preload: path.join(__dirname, 'resources', 'app', 'js', 'preload.js')
    }
  });
  win.loadFile(path.join('resources', 'app', 'templates', 'login.html'));
}

function startPython() {
  const script = path.join(__dirname, 'python', 'main.py');
  const py = path.join(__dirname, 'resources', 'app', 'script', 'python-3.11.0-embed-amd64', 'python.exe');
  const cmd = process.platform === 'win32' ? py : 'python3';
  const args = [script];
  pythonProcess = spawn(cmd, args);
  pythonProcess.stdout.on('data', (data) => console.log(`PYTHON: ${data}`));
  pythonProcess.stderr.on('data', (data) => console.error(`PYTHON ERR: ${data}`));
}

app.whenReady().then(() => {
  setupPython();
  startPython();
  createWindow();
  autoUpdater.checkForUpdatesAndNotify();
});

app.on('before-quit', () => {
  if (pythonProcess) pythonProcess.kill();
});

ipcMain.handle('get-device-info', () => ({ platform: process.platform }));
ipcMain.handle('run-login', async (_e, data) => {
  return fetch('http://127.0.0.1:3030/run-login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  }).then(res => res.json());
});
ipcMain.handle('run-bot', async (_e, data) => {
  return fetch('http://127.0.0.1:3030/run-bot', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  }).then(res => res.json());
});
ipcMain.handle('run-post', async (_e, data) => {
  return fetch('http://127.0.0.1:3030/run-post', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  }).then(res => res.json());
});
