const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const desktopDir = path.join(rootDir, 'desktop-app');
const desktopSrcDir = path.join(desktopDir, 'src');
const desktopBinDir = path.join(desktopDir, 'bin');
const desktopPhpDir = path.join(desktopBinDir, 'php');

const IGNORE_LIST = [
  '.git',
  '.github',
  'desktop-app',
  'dist',
  'node_modules',
  'scratch',
  'scripts',
  '.env',
  '.gitignore',
  'package-lock.json',
  'package.json'
];

const IGNORE_PATTERNS = [
  /^backup_pospharma_.*\.sql$/,
  /^\.DS_Store$/
];

function shouldIgnore(name) {
  if (IGNORE_LIST.includes(name)) return true;
  for (const pattern of IGNORE_PATTERNS) {
    if (pattern.test(name)) return true;
  }
  return false;
}

// Helper to copy directory recursively
function copyRecursiveSync(src, dest) {
  const exists = fs.existsSync(src);
  const stats = exists && fs.statSync(src);
  const isDirectory = exists && stats.isDirectory();

  if (isDirectory) {
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    fs.readdirSync(src).forEach((childItemName) => {
      if (shouldIgnore(childItemName)) return;
      copyRecursiveSync(
        path.join(src, childItemName),
        path.join(dest, childItemName)
      );
    });
  } else {
    const filename = path.basename(src);
    if (!shouldIgnore(filename)) {
      fs.copyFileSync(src, dest);
    }
  }
}

console.log('🧹 Preparing desktop app source directories...');
// Preserve any user-uploaded assets from desktop-app/src before cleaning
['assets/images', 'assets/media'].forEach((subDir) => {
  const existingUploadDir = path.join(desktopSrcDir, subDir);
  const rootUploadDir = path.join(rootDir, subDir);
  if (fs.existsSync(existingUploadDir)) {
    fs.mkdirSync(rootUploadDir, { recursive: true });
    fs.readdirSync(existingUploadDir).forEach((file) => {
      const srcFile = path.join(existingUploadDir, file);
      const destFile = path.join(rootUploadDir, file);
      if (!fs.existsSync(destFile) && fs.statSync(srcFile).isFile()) {
        fs.copyFileSync(srcFile, destFile);
      }
    });
  }
});

if (fs.existsSync(desktopSrcDir)) {
  try {
    fs.rmSync(desktopSrcDir, { recursive: true, force: true });
  } catch (e) {
    console.warn('⚠️ Could not fully remove desktop-app/src (in use), overwriting files in place...');
  }
}
fs.mkdirSync(desktopSrcDir, { recursive: true });

console.log('📦 Copying PHP project code into desktop-app/src...');
copyRecursiveSync(rootDir, desktopSrcDir);

// Create empty logs directory inside desktop src
const logDir = path.join(desktopSrcDir, 'logs');
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir);
}
fs.writeFileSync(path.join(logDir, '.gitkeep'), '');

// Attempt to copy PHP binary from local XAMPP to bundle it as portable
console.log('🔍 Checking for local XAMPP PHP installation to bundle as Portable PHP...');
const xamppPhpDir = 'C:\\xampp\\php';

if (fs.existsSync(xamppPhpDir)) {
  console.log(`💡 Found local PHP in ${xamppPhpDir}. Bundling...`);
  try {
    if (fs.existsSync(desktopPhpDir)) {
      fs.rmSync(desktopPhpDir, { recursive: true, force: true });
    }
    fs.mkdirSync(desktopPhpDir, { recursive: true });

  // Copy essential PHP executables & dlls
  const filesToCopy = [
    'php.exe',
    'php.ini-production',
    'php.ini-development'
  ];

  // Search and copy dll files at root level
  fs.readdirSync(xamppPhpDir).forEach((file) => {
    if (file.endsWith('.dll')) {
      filesToCopy.push(file);
    }
  });

  filesToCopy.forEach((file) => {
    const srcPath = path.join(xamppPhpDir, file);
    const destPath = path.join(desktopPhpDir, file);
    if (fs.existsSync(srcPath)) {
      fs.copyFileSync(srcPath, destPath);
    }
  });

  // Copy extensions directory (ext/)
  const xamppExtDir = path.join(xamppPhpDir, 'ext');
  const desktopExtDir = path.join(desktopPhpDir, 'ext');
  if (fs.existsSync(xamppExtDir)) {
    fs.mkdirSync(desktopExtDir, { recursive: true });
    const extFiles = ['php_pdo_mysql.dll', 'php_pdo_sqlite.dll', 'php_sqlite3.dll', 'php_mbstring.dll', 'php_fileinfo.dll', 'php_openssl.dll', 'php_curl.dll'];
    extFiles.forEach((file) => {
      const srcPath = path.join(xamppExtDir, file);
      const destPath = path.join(desktopExtDir, file);
      if (fs.existsSync(srcPath)) {
        fs.copyFileSync(srcPath, destPath);
      }
    });
  }

  // Create custom portable php.ini
  console.log('📝 Creating customized portable php.ini...');
  const phpIniContent = `; Portable php.ini for POS Pharma Desktop App
extension_dir = "ext"
extension = pdo_mysql
extension = pdo_sqlite
extension = sqlite3
extension = mbstring
extension = fileinfo
extension = openssl
extension = curl

max_execution_time = 300
memory_limit = 256M
post_max_size = 32M
upload_max_filesize = 32M
default_charset = "UTF-8"
`;
  fs.writeFileSync(path.join(desktopPhpDir, 'php.ini'), phpIniContent, 'utf8');
  console.log('✨ Portable PHP bundled successfully!');
  } catch (err) {
    console.warn('⚠️ Portable PHP is currently running/locked, skipping PHP binary re-copy.');
  }
} else {
  console.log('⚠️ Local XAMPP PHP (C:\\xampp\\php) not found.');
  console.log('👉 The app will use the system PHP in the PATH during local runs.');
  console.log('👉 For packaging, make sure to manually copy a portable Windows PHP distribution into: \n   desktop-app/bin/php/');
}

// Bundle Portable MariaDB/MySQL
console.log('🔍 Checking for local XAMPP MariaDB/MySQL to bundle as Portable Database...');
const xamppMysqlDir = 'C:\\xampp\\mysql';
const desktopMysqlDir = path.join(desktopBinDir, 'mysql');

if (fs.existsSync(xamppMysqlDir)) {
  console.log(`💡 Found local MySQL in ${xamppMysqlDir}. Bundling...`);
  try {
  const mysqlBinDir = path.join(desktopMysqlDir, 'bin');
  const mysqlShareDir = path.join(desktopMysqlDir, 'share');
  const mysqlDataTemplateDir = path.join(desktopMysqlDir, 'data_template');

  fs.mkdirSync(mysqlBinDir, { recursive: true });
  fs.mkdirSync(mysqlShareDir, { recursive: true });
  fs.mkdirSync(mysqlDataTemplateDir, { recursive: true });

  const mysqlExecs = ['mysqld.exe', 'mysql.exe', 'mysqladmin.exe', 'mysqldump.exe'];
  mysqlExecs.forEach((file) => {
    const src = path.join(xamppMysqlDir, 'bin', file);
    if (fs.existsSync(src)) fs.copyFileSync(src, path.join(mysqlBinDir, file));
  });

  fs.readdirSync(path.join(xamppMysqlDir, 'bin')).forEach((file) => {
    if (file.endsWith('.dll')) {
      const src = path.join(xamppMysqlDir, 'bin', file);
      const dest = path.join(mysqlBinDir, file);
      if (!fs.existsSync(dest)) fs.copyFileSync(src, dest);
    }
  });

  copyRecursiveSync(path.join(xamppMysqlDir, 'share'), mysqlShareDir);
  copyRecursiveSync(path.join(xamppMysqlDir, 'backup'), mysqlDataTemplateDir);

  console.log('✨ Portable MariaDB bundled successfully!');
  } catch (err) {
    console.warn('⚠️ Portable MariaDB is currently running/locked, skipping MySQL binary re-copy.');
  }
} else {
  console.log('⚠️ Local XAMPP MySQL not found. Skipping MySQL bundle.');
}

console.log('\n🎉 Desktop app source files assembled in "desktop-app/".');
console.log('\n======================================================');
console.log('🚀 HOW TO RUN AND BUILD THE DESKTOP APP:');
console.log('1. Open your terminal and change directory to "desktop-app":');
console.log('   cd desktop-app');
console.log('2. Install development and production dependencies:');
console.log('   npm install');
console.log('3. Run the desktop application locally:');
console.log('   npm start');
console.log('4. Package the application into a single Windows .exe installer:');
console.log('   npm run dist');
console.log('======================================================\n');
