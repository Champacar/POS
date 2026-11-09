const { app, BrowserWindow, Menu, ipcMain, nativeImage } = require('electron');
const path = require('path');
const fs = require('fs');
const { spawn } = require('child_process');
const net = require('net');
const http = require('http');
const kill = require('tree-kill');

/**
 * Resolve the latest custom favicon or logo for the desktop application window
 */
function getAppIconPath() {
  const candidateDirs = [
    path.join(__dirname, 'src', 'assets', 'images'),
    path.join(__dirname, 'assets', 'images'),
    'D:/Project/pospharma/assets/images',
    'D:/Project/pospharma/desktop-app/src/assets/images',
    'D:/Project/pospharma/dist/desktop/win-unpacked/resources/app/src/assets/images'
  ];

  for (const dir of candidateDirs) {
    try {
      if (fs.existsSync(dir)) {
        const files = fs.readdirSync(dir);
        // 1. Check for custom favicon uploaded by user
        const favicons = files.filter(f => f.startsWith('favicon_') && (f.endsWith('.png') || f.endsWith('.ico') || f.endsWith('.jpg')));
        if (favicons.length > 0) {
          favicons.sort();
          return path.join(dir, favicons[favicons.length - 1]);
        }
        // 2. Check for custom logo uploaded by user
        const logos = files.filter(f => f.startsWith('logo_') && (f.endsWith('.png') || f.endsWith('.ico') || f.endsWith('.jpg')));
        if (logos.length > 0) {
          logos.sort();
          return path.join(dir, logos[logos.length - 1]);
        }
        // 3. Fallback to favicon.png
        if (fs.existsSync(path.join(dir, 'favicon.png'))) {
          return path.join(dir, 'favicon.png');
        }
      }
    } catch (e) {}
  }
  return null;
}

// Disable top application menu bar (File, Edit, View, Window, Help) globally
if (Menu && typeof Menu.setApplicationMenu === 'function') {
  try { Menu.setApplicationMenu(null); } catch (e) {}
}

// Enable silent kiosk auto-printing specifically for Desktop POS (bypasses Chromium print dialog)
app.commandLine.appendSwitch('kiosk-printing');

let mainWindow;
let splashWindow;
let phpProcess = null;
let mysqlProcess = null;
let dbConfig = null;
const configPath = path.join(app.getPath('userData'), 'pos-config.json');

// Default database configuration (Standalone Offline Mode by default)
const defaultConfig = {
  db_mode: 'embedded', // 'embedded' (Portable MariaDB), 'sqlite' (Single-file .db), or 'external' (XAMPP / LAN)
  db_host: '127.0.0.1',
  db_port: '3307',
  embedded_port: 3307,
  db_name: 'pospharma',
  db_user: 'root',
  db_pass: '',
  php_port: 8082,
  sqlite_path: ''
};

// Load configuration
function loadConfig() {
  if (fs.existsSync(configPath)) {
    try {
      const data = fs.readFileSync(configPath, 'utf8');
      const parsed = JSON.parse(data);
      dbConfig = { ...defaultConfig, ...parsed };
      if (!parsed.db_mode) {
        dbConfig.db_mode = 'embedded';
        dbConfig.db_port = '3307';
        dbConfig.db_pass = '';
        saveConfig(dbConfig);
      }
    } catch (e) {
      dbConfig = { ...defaultConfig };
    }
  } else {
    dbConfig = { ...defaultConfig };
    saveConfig(dbConfig);
  }
}

// Save configuration
function saveConfig(config) {
  try {
    fs.writeFileSync(configPath, JSON.stringify(config, null, 2), 'utf8');
  } catch (e) {
    console.error('Failed to save config:', e);
  }
}

// Helper: Recursive copy
function copyRecursiveSync(src, dest) {
  if (!fs.existsSync(src)) return;
  const stats = fs.statSync(src);
  if (stats.isDirectory()) {
    if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
    fs.readdirSync(src).forEach((child) => {
      copyRecursiveSync(path.join(src, child), path.join(dest, child));
    });
  } else {
    fs.copyFileSync(src, dest);
  }
}

// Determine PHP executable path
function getPhpPath() {
  const localPath = path.join(__dirname, 'bin', 'php', 'php.exe');
  if (fs.existsSync(localPath)) {
    return localPath;
  }
  return 'php';
}

// Determine Embedded MariaDB paths
function getMysqlPaths() {
  const base = path.join(__dirname, 'bin', 'mysql');
  return {
    base,
    bin: path.join(base, 'bin'),
    mysqld: path.join(base, 'bin', 'mysqld.exe'),
    mysql: path.join(base, 'bin', 'mysql.exe'),
    mysqladmin: path.join(base, 'bin', 'mysqladmin.exe'),
    share: path.join(base, 'share'),
    dataTemplate: path.join(base, 'data_template')
  };
}

// Initialize Embedded MariaDB user data directory
function initEmbeddedMysqlData(port) {
  const mysqlPaths = getMysqlPaths();
  const userDataMysql = path.join(app.getPath('userData'), 'mysql_data');
  const iniPath = path.join(app.getPath('userData'), 'mysql.ini');
  let isFirstRun = false;

  if (!fs.existsSync(userDataMysql) || !fs.existsSync(path.join(userDataMysql, 'mysql'))) {
    fs.mkdirSync(userDataMysql, { recursive: true });
    if (fs.existsSync(mysqlPaths.dataTemplate)) {
      console.log('Copying clean MariaDB data template to userData...');
      copyRecursiveSync(mysqlPaths.dataTemplate, userDataMysql);
    }
    isFirstRun = true;
  }

  // Generate portable my.ini pointing to userData
  const iniContent = `[mysqld]
port=${port}
socket="${userDataMysql.replace(/\\/g, '/')}/mysql.sock"
basedir="${mysqlPaths.base.replace(/\\/g, '/')}"
datadir="${userDataMysql.replace(/\\/g, '/')}"
pid_file="${userDataMysql.replace(/\\/g, '/')}/mysql.pid"
log_error="${userDataMysql.replace(/\\/g, '/')}/mysql_error.log"
character-set-server=utf8mb4
collation-server=utf8mb4_general_ci
default_storage_engine=InnoDB
bind-address=127.0.0.1
max_allowed_packet=16M
innodb_buffer_pool_size=16M
innodb_data_file_path=ibdata1:10M:autoextend
`;
  fs.writeFileSync(iniPath, iniContent, 'utf8');

  return { iniPath, userDataMysql, isFirstRun };
}

// Start Embedded MariaDB Server
function startEmbeddedMysql(port) {
  return new Promise(async (resolve, reject) => {
    const mysqlPaths = getMysqlPaths();
    if (!fs.existsSync(mysqlPaths.mysqld)) {
      return reject(new Error('Embedded MariaDB binary (mysqld.exe) not found in bin/mysql/bin'));
    }

    // Check if already listening on port
    const alreadyRunning = await checkDatabaseConnection(port, '127.0.0.1');
    if (alreadyRunning) {
      console.log(`Embedded MariaDB is already running on port ${port}`);
      return resolve(true);
    }

    const { iniPath, userDataMysql } = initEmbeddedMysqlData(port);
    const dbFolderExists = fs.existsSync(path.join(userDataMysql, 'pospharma'));

    console.log(`Starting Embedded MariaDB on port ${port}...`);
    mysqlProcess = spawn(mysqlPaths.mysqld, [
      `--defaults-file=${iniPath}`,
      '--console'
    ]);

    mysqlProcess.stdout.on('data', (d) => console.log(`[MySQL STDOUT]: ${d}`));
    mysqlProcess.stderr.on('data', (d) => console.log(`[MySQL STDERR]: ${d}`));
    mysqlProcess.on('error', (err) => console.error('Failed to spawn MariaDB:', err));

    // Poll until MariaDB is accepting connections
    let attempts = 0;
    const interval = setInterval(async () => {
      attempts++;
      const connected = await checkDatabaseConnection(port, '127.0.0.1');
      if (connected) {
        clearInterval(interval);
        console.log('Embedded MariaDB is ready!');

        // Ensure pospharma database and tables exist
        const fullSqlScript = path.join(__dirname, 'src', 'pospharma.sql');
        const fallbackSqlScript = path.join(__dirname, 'src', 'database', 'pospharma.sql');
        const sqlScript = fs.existsSync(fullSqlScript) ? fullSqlScript : fallbackSqlScript;
        if (!dbFolderExists && fs.existsSync(sqlScript) && fs.existsSync(mysqlPaths.mysql)) {
          console.log('Initializing pospharma database schema...');
          const createDbProc = spawn(mysqlPaths.mysql, [
            '-P', String(port),
            '-h', '127.0.0.1',
            '-u', 'root',
            '-e', 'CREATE DATABASE IF NOT EXISTS pospharma CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;'
          ]);

          createDbProc.on('close', () => {
            const importProc = spawn(mysqlPaths.mysql, [
              '-P', String(port),
              '-h', '127.0.0.1',
              '-u', 'root',
              '--default-character-set=utf8mb4',
              'pospharma',
              '-e', `SOURCE ${sqlScript.replace(/\\/g, '/')};`
            ]);

            importProc.on('close', () => {
              // Ensure product_batches, purchase_orders, purchase_order_items, suppliers tables exist
              const extraTablesSql = `
                CREATE TABLE IF NOT EXISTS suppliers (
                  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  name varchar(255) NOT NULL,
                  contact_name varchar(150) DEFAULT NULL,
                  phone varchar(50) DEFAULT NULL,
                  email varchar(150) DEFAULT NULL,
                  address text DEFAULT NULL,
                  is_active tinyint(1) NOT NULL DEFAULT 1,
                  created_at timestamp NOT NULL DEFAULT current_timestamp(),
                  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS product_batches (
                  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  product_id int(11) NOT NULL,
                  batch_number varchar(50) NOT NULL,
                  cost_price decimal(15,2) NOT NULL DEFAULT 0.00,
                  selling_price decimal(15,2) NOT NULL DEFAULT 0.00,
                  initial_quantity int(11) NOT NULL DEFAULT 0,
                  quantity int(11) NOT NULL DEFAULT 0,
                  expiry_date date NOT NULL,
                  created_at timestamp NOT NULL DEFAULT current_timestamp(),
                  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS purchase_orders (
                  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  po_number varchar(50) NOT NULL UNIQUE,
                  supplier_id int(11) DEFAULT NULL,
                  user_id int(11) DEFAULT NULL,
                  total_amount decimal(15,2) NOT NULL DEFAULT 0.00,
                  status varchar(30) NOT NULL DEFAULT 'draft',
                  note text DEFAULT NULL,
                  created_at timestamp NOT NULL DEFAULT current_timestamp(),
                  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS purchase_order_items (
                  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  po_id int(11) NOT NULL,
                  product_id int(11) DEFAULT NULL,
                  product_name varchar(255) NOT NULL,
                  quantity int(11) NOT NULL,
                  unit_price decimal(15,2) NOT NULL DEFAULT 0.00,
                  FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
              `;
              const extraProc = spawn(mysqlPaths.mysql, [
                '-P', String(port),
                '-h', '127.0.0.1',
                '-u', 'root',
                'pospharma',
                '-e', extraTablesSql
              ]);
              extraProc.on('close', () => {
                console.log('Database initialization complete!');
                resolve(true);
              });
            });
          });
        } else {
          resolve(true);
        }
      } else if (attempts >= 30) {
        clearInterval(interval);
        reject(new Error('Embedded MariaDB startup timed out.'));
      }
    }, 500);
  });
}

// Stop Embedded MariaDB cleanly
function stopEmbeddedMysql() {
  return new Promise((resolve) => {
    const mysqlPaths = getMysqlPaths();
    const port = dbConfig ? (dbConfig.embedded_port || 3307) : 3307;

    if (fs.existsSync(mysqlPaths.mysqladmin)) {
      try {
        const p = spawn(mysqlPaths.mysqladmin, [
          '-P', String(port),
          '-h', '127.0.0.1',
          '-u', 'root',
          'shutdown'
        ]);
        p.on('close', () => {
          if (mysqlProcess) {
            kill(mysqlProcess.pid, () => resolve());
            mysqlProcess = null;
          } else {
            resolve();
          }
        });
        setTimeout(() => {
          if (mysqlProcess) {
            kill(mysqlProcess.pid, () => resolve());
            mysqlProcess = null;
          } else {
            resolve();
          }
        }, 2500);
        return;
      } catch (e) {}
    }

    if (mysqlProcess) {
      kill(mysqlProcess.pid, () => resolve());
      mysqlProcess = null;
    } else {
      resolve();
    }
  });
}

// Start PHP Server
function startPhpServer(port) {
  return new Promise((resolve, reject) => {
    const phpPath = getPhpPath();
    const srcDir = path.join(__dirname, 'src');

    const isSqlite = dbConfig.db_mode === 'sqlite';
    const isEmbedded = dbConfig.db_mode === 'embedded';
    const dbHost = isEmbedded ? '127.0.0.1' : dbConfig.db_host;
    const dbPort = isEmbedded ? String(dbConfig.embedded_port || 3307) : String(dbConfig.db_port);
    const dbUser = isEmbedded ? 'root' : dbConfig.db_user;
    const dbPass = isEmbedded ? '' : dbConfig.db_pass;
    const sqlitePath = dbConfig.sqlite_path || path.join(app.getPath('userData'), 'pospharma.db');

    console.log(`Starting PHP server... \nExecutable: ${phpPath}\nSource: ${srcDir}\nPort: ${port}\nMode: ${dbConfig.db_mode}`);

    phpProcess = spawn(phpPath, [
      '-S', `127.0.0.1:${port}`,
      '-t', srcDir,
      '-d', 'display_errors=0'
    ], {
      env: {
        ...process.env,
        APP_ENV: 'production',
        BASE_URL: '/',
        SYNC_MODE: 'standalone',
        DB_CONNECTION: isSqlite ? 'sqlite' : 'mysql',
        SQLITE_PATH: sqlitePath,
        DB_HOST: dbHost,
        DB_PORT: dbPort,
        DB_NAME: dbConfig.db_name || 'pospharma',
        DB_USER: dbUser,
        DB_PASS: dbPass === '' ? 'NONE' : dbPass
      }
    });

    phpProcess.stdout.on('data', (data) => {
      console.log(`[PHP STDOUT]: ${data}`);
    });

    phpProcess.stderr.on('data', (data) => {
      console.log(`[PHP STDERR]: ${data}`);
    });

    phpProcess.on('error', (err) => {
      console.error('Failed to start PHP process:', err);
      reject(err);
    });

    // Check if PHP server becomes responsive
    let attempts = 0;
    const interval = setInterval(() => {
      attempts++;
      const req = http.get(`http://127.0.0.1:${port}/login.php`, (res) => {
        clearInterval(interval);
        resolve(true);
      });
      req.on('error', () => {
        if (attempts >= 15) {
          clearInterval(interval);
          reject(new Error('PHP server did not respond in time.'));
        }
      });
      req.end();
    }, 500);
  });
}

// Check database TCP port
function checkDatabaseConnection(port, host) {
  const targetPort = Number(port || dbConfig.db_port);
  const targetHost = host || dbConfig.db_host;
  return new Promise((resolve) => {
    const socket = new net.Socket();
    socket.setTimeout(1500);

    socket.on('connect', () => {
      socket.destroy();
      resolve(true);
    });

    socket.on('timeout', () => {
      socket.destroy();
      resolve(false);
    });

    socket.on('error', () => {
      socket.destroy();
      resolve(false);
    });

    socket.connect(targetPort, targetHost);
  });
}

// Create Windows
function createWindows() {
  const appIconPath = getAppIconPath();
  const appIcon = appIconPath ? nativeImage.createFromPath(appIconPath) : undefined;

  // 1. Create Splash Window
  splashWindow = new BrowserWindow({
    width: 500,
    height: 350,
    frame: false,
    transparent: true,
    alwaysOnTop: true,
    icon: appIcon,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true
    }
  });
  splashWindow.loadFile(path.join(__dirname, 'splash.html'));

  // 2. Create Main Window (hidden initially)
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 820,
    show: false,
    autoHideMenuBar: true,
    fullscreenable: true,
    title: 'ລະບົບຂາຍຢາດີ - POS Pharmacy (Offline)',
    icon: appIcon,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true
    }
  });

  if (appIcon && !appIcon.isEmpty()) {
    mainWindow.setIcon(appIcon);
  }

  mainWindow.setMenuBarVisibility(false);
  if (typeof mainWindow.removeMenu === 'function') {
    mainWindow.removeMenu();
  }

  // Support F11 shortcut to toggle true fullscreen mode
  mainWindow.webContents.on('before-input-event', (event, input) => {
    if (input.key === 'F11' && input.type === 'keyDown') {
      mainWindow.setFullScreen(!mainWindow.isFullScreen());
      event.preventDefault();
    }
  });

  // Dynamically update window icon whenever page favicon updates
  mainWindow.webContents.on('page-favicon-updated', (event, favicons) => {
    if (favicons && favicons.length > 0) {
      try {
        const faviconUrl = favicons[0];
        if (faviconUrl.startsWith('http://') || faviconUrl.startsWith('https://')) {
          http.get(faviconUrl, (res) => {
            const chunks = [];
            res.on('data', chunk => chunks.push(chunk));
            res.on('end', () => {
              const buffer = Buffer.concat(chunks);
              const img = nativeImage.createFromBuffer(buffer);
              if (!img.isEmpty() && mainWindow) {
                mainWindow.setIcon(img);
              }
            });
          }).on('error', (e) => console.log('Favicon load error:', e));
        } else if (fs.existsSync(faviconUrl)) {
          const img = nativeImage.createFromPath(faviconUrl);
          if (!img.isEmpty() && mainWindow) {
            mainWindow.setIcon(img);
          }
        }
      } catch (err) {
        console.error('Failed to update window icon from favicon:', err);
      }
    }
  });

  mainWindow.webContents.setWindowOpenHandler(() => {
    return {
      action: 'allow',
      overrideBrowserWindowOptions: {
        preload: path.join(__dirname, 'preload.js'),
        webPreferences: {
          nodeIntegration: false,
          contextIsolation: true
        }
      }
    };
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // Start initialization check
  initSystem();
}

async function initSystem() {
  loadConfig();

  // Step 1: Initialize Database according to mode
  if (dbConfig.db_mode === 'embedded') {
    const port = dbConfig.embedded_port || 3307;
    try {
      await startEmbeddedMysql(port);
    } catch (err) {
      console.error('Embedded MySQL failed, falling back to SQLite mode:', err);
      // Automatic fallback to SQLite so user never gets stuck offline!
      dbConfig.db_mode = 'sqlite';
      saveConfig(dbConfig);
    }
  } else if (dbConfig.db_mode === 'sqlite') {
    console.log('Running in standalone SQLite mode.');
  } else {
    // External MySQL mode
    const dbConnected = await checkDatabaseConnection(dbConfig.db_port, dbConfig.db_host);
    if (!dbConnected) {
      showDatabaseErrorScreen('cannot_connect_db');
      return;
    }
  }

  // Step 2: Start PHP server
  try {
    if (phpProcess) {
      kill(phpProcess.pid);
      phpProcess = null;
    }
    await startPhpServer(dbConfig.php_port);

    // PHP started, load app
    mainWindow.loadURL(`http://127.0.0.1:${dbConfig.php_port}/login.php`);
    mainWindow.once('ready-to-show', () => {
      if (splashWindow) {
        splashWindow.close();
        splashWindow = null;
      }
      const iconPath = getAppIconPath();
      if (iconPath) {
        const iconImg = nativeImage.createFromPath(iconPath);
        if (!iconImg.isEmpty() && mainWindow) {
          mainWindow.setIcon(iconImg);
        }
      }
      mainWindow.setMenuBarVisibility(false);
      mainWindow.maximize();
      mainWindow.show();
    });
  } catch (err) {
    showDatabaseErrorScreen('php_startup_error');
  }
}

function showDatabaseErrorScreen(errorType) {
  if (splashWindow) {
    splashWindow.close();
    splashWindow = null;
  }

  mainWindow.loadFile(path.join(__dirname, 'db-config.html'));
  mainWindow.once('ready-to-show', () => {
    mainWindow.setMenuBarVisibility(false);
    mainWindow.maximize();
    mainWindow.show();
    mainWindow.webContents.send('init-data', {
      config: dbConfig,
      errorType: errorType
    });
  });
}

// Handle Database Settings IPC communication
ipcMain.on('save-db-settings', async (event, newConfig) => {
  dbConfig = { ...dbConfig, ...newConfig };
  saveConfig(dbConfig);

  const appIconPath = getAppIconPath();
  const appIcon = appIconPath ? nativeImage.createFromPath(appIconPath) : undefined;

  if (dbConfig.db_mode === 'embedded' || dbConfig.db_mode === 'sqlite') {
    mainWindow.hide();
    splashWindow = new BrowserWindow({
      width: 500,
      height: 350,
      frame: false,
      transparent: true,
      alwaysOnTop: true,
      icon: appIcon
    });
    splashWindow.loadFile(path.join(__dirname, 'splash.html'));
    initSystem();
    return;
  }

  // External mode check
  const dbConnected = await checkDatabaseConnection(dbConfig.db_port, dbConfig.db_host);
  if (dbConnected) {
    mainWindow.hide();
    splashWindow = new BrowserWindow({
      width: 500,
      height: 350,
      frame: false,
      transparent: true,
      alwaysOnTop: true,
      icon: appIcon
    });
    splashWindow.loadFile(path.join(__dirname, 'splash.html'));
    initSystem();
  } else {
    event.reply('save-result', {
      success: false,
      message: 'ຍັງບໍ່ສາມາດເຊື່ອມຕໍ່ກັບ External MySQL ໄດ້, ກະລຸນາກວດສອບຄືນ ຫຼື ເລືອກໂໝດ Offline Built-in!'
    });
  }
});

// ===================================================
// POS Silent Receipt Printing & Printer Management
// ===================================================
ipcMain.handle('get-printers', async () => {
  try {
    if (mainWindow && mainWindow.webContents) {
      return await mainWindow.webContents.getPrintersAsync();
    }
  } catch (err) {
    console.error('Error fetching system printers:', err);
  }
  return [];
});

// POS Desktop Printing Diagnostic Logger
function logDesktopPrint(msg) {
  const line = `[${new Date().toISOString()}] ${msg}\n`;
  try { fs.appendFileSync('D:/Project/pospharma/desktop_print.log', line, 'utf8'); } catch (e) {}
  try { fs.appendFileSync(path.join(app.getPath('userData'), 'desktop_print.log'), line, 'utf8'); } catch (e) {}
  console.log(msg);
}

ipcMain.handle('print-receipt-silent', async (event, options = {}) => {
  const { url, html, deviceName } = options;
  if (!url && !html) {
    logDesktopPrint('Silent print aborted: no URL or HTML content provided');
    return { success: false, message: 'URL ຫຼື ຂໍ້ມູນພິມວ່າງເປົ່າ' };
  }

  logDesktopPrint(`Silent print requested: url=${url || 'none'}, hasHtml=${Boolean(html)}, requestedPrinter=${deviceName || 'Default'}`);

  let printWin = new BrowserWindow({
    show: false,
    width: 380,
    height: 800,
    webPreferences: {
      backgroundThrottling: false,
      nodeIntegration: false,
      contextIsolation: true
    }
  });

  return new Promise((resolve) => {
    let resolved = false;
    const safeResolve = (res) => {
      if (!resolved) {
        resolved = true;
        logDesktopPrint(`Silent print returning result: success=${res.success}, message=${res.message || 'ok'}`);
        resolve(res);
        setTimeout(() => {
          try {
            if (printWin && !printWin.isDestroyed()) {
              printWin.close();
            }
          } catch (e) {}
          printWin = null;
        }, 2000);
      }
    };

    const timeout = setTimeout(() => {
      logDesktopPrint('Silent print timed out after 10s');
      safeResolve({ success: false, message: 'Print timeout (10s)' });
    }, 10000);

    printWin.webContents.on('did-fail-load', (errEvent, errorCode, errorDescription, validatedURL) => {
      clearTimeout(timeout);
      logDesktopPrint(`Silent print did-fail-load: code=${errorCode}, desc=${errorDescription}, url=${validatedURL}`);
      safeResolve({ success: false, message: `Load failed: ${errorDescription}` });
    });

    printWin.webContents.on('did-finish-load', async () => {
      try {
        logDesktopPrint('did-finish-load fired in printWin');
        // Small delay for CSS layout and fonts to settle
        await new Promise(r => setTimeout(r, 350));

        const printOptions = {
          silent: true,
          printBackground: true,
          margins: { marginType: 'none' },
          landscape: Boolean(options.landscape)
        };
        if (options.pageSize && options.pageSize.width && options.pageSize.height) {
          printOptions.pageSize = options.pageSize;
        }

        // Query available system printers to ensure valid target device
        try {
          const printers = await printWin.webContents.getPrintersAsync();
          logDesktopPrint(`Found ${printers.length} system printers: ` + printers.map(p => `${p.name}${p.isDefault ? ' (Default)' : ''}`).join(', '));
          
          if (deviceName && deviceName.trim()) {
            const found = printers.find(p => p.name.trim().toLowerCase() === deviceName.trim().toLowerCase());
            if (found) {
              printOptions.deviceName = found.name;
              logDesktopPrint(`Matched target printer: ${found.name}`);
            } else {
              logDesktopPrint(`Printer "${deviceName}" not found among system printers, falling back to default printer.`);
            }
          }
        } catch (pe) {
          logDesktopPrint(`Could not enumerate printers: ${pe.message}`);
          if (deviceName && deviceName.trim()) {
            printOptions.deviceName = deviceName.trim();
          }
        }

        logDesktopPrint(`Calling printWin.webContents.print with device: ${printOptions.deviceName || 'OS Default'}`);

        printWin.webContents.print(printOptions, (success, failureReason) => {
          clearTimeout(timeout);
          logDesktopPrint(`webContents.print callback: success=${success}, failureReason=${failureReason || 'none'}`);
          if (!success) {
            safeResolve({ success: false, message: failureReason || 'Printing failed in driver' });
          } else {
            safeResolve({ success: true });
          }
        });
      } catch (err) {
        clearTimeout(timeout);
        logDesktopPrint(`Error inside did-finish-load: ${err.message}`);
        safeResolve({ success: false, message: err.message });
      }
    });

    // Prioritize direct HTTP URL so fonts, images, and styles load reliably without CORS restrictions
    if (url) {
      logDesktopPrint(`Loading direct URL: ${url}`);
      printWin.loadURL(url);
    } else {
      logDesktopPrint('Loading data URL with HTML content');
      printWin.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(html)}`);
    }
  });
});

ipcMain.handle('print-current-page-silent', async (event, options = {}) => {
  const contents = event.sender;
  let deviceName = options && options.deviceName ? options.deviceName.trim() : '';
  return new Promise(async (resolve) => {
    const printOptions = {
      silent: true,
      printBackground: true,
      margins: { marginType: 'none' }
    };

    try {
      const printers = await contents.getPrintersAsync();
      if (deviceName) {
        const found = printers.find(p => p.name.trim().toLowerCase() === deviceName.toLowerCase());
        if (found) {
          printOptions.deviceName = found.name;
        } else {
          console.warn(`Requested printer "${deviceName}" not found for current page, using default.`);
        }
      }
    } catch (e) {
      if (deviceName) printOptions.deviceName = deviceName;
    }

    contents.print(printOptions, (success, failureReason) => {
      if (!success) {
        console.error('Silent print of current page failed:', failureReason);
        resolve({ success: false, message: failureReason });
      } else {
        console.log('Silent print of current page succeeded');
        resolve({ success: true });
      }
    });
  });
});

// App Lifecycle
app.whenReady().then(() => {
  createWindows();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindows();
  });
});

app.on('window-all-closed', async () => {
  console.log('Shutting down services...');
  if (phpProcess) {
    kill(phpProcess.pid);
    phpProcess = null;
  }
  await stopEmbeddedMysql();
  app.quit();
});
