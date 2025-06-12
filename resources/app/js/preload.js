const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('api', {
  getDeviceInfo: () => ipcRenderer.invoke('get-device-info'),
  runLogin: (credentials) => ipcRenderer.invoke('run-login', credentials),
  runBot: (options) => ipcRenderer.invoke('run-bot', options),
  runPost: (data) => ipcRenderer.invoke('run-post', data)
});
