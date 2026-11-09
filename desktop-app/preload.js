const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('api', {
  saveDbSettings: (config) => ipcRenderer.send('save-db-settings', config),
  onInitData: (callback) => ipcRenderer.on('init-data', (event, data) => callback(data)),
  onSaveResult: (callback) => ipcRenderer.on('save-result', (event, data) => callback(data)),
  printReceiptSilent: (options) => ipcRenderer.invoke('print-receipt-silent', options),
  printCurrentPageSilent: (options) => ipcRenderer.invoke('print-current-page-silent', options),
  getPrinters: () => ipcRenderer.invoke('get-printers'),
  isDesktop: true
});


