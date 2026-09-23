const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('digsignagePlayer', {
    platform: 'windows',
    version: '1.0.0',
    captureScreenshot: () => ipcRenderer.invoke('player:screenshot'),
    restart: () => ipcRenderer.invoke('player:restart'),
    start: (serverUrl) => ipcRenderer.invoke('player:start', serverUrl),
    info: () => ipcRenderer.invoke('player:info'),
});
