const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');
const distDir = path.join(rootDir, 'dist');
const tempDir = path.join(distDir, 'pospharma-web');
const zipFile = path.join(distDir, 'pospharma-web.zip');

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
    // If not ignored, copy the file
    const filename = path.basename(src);
    if (!shouldIgnore(filename)) {
      fs.copyFileSync(src, dest);
    }
  }
}

console.log('🧹 Preparing build directories...');
if (fs.existsSync(tempDir)) {
  fs.rmSync(tempDir, { recursive: true, force: true });
}
if (fs.existsSync(zipFile)) {
  fs.unlinkSync(zipFile);
}
if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

console.log('📦 Copying files to temporary build folder...');
copyRecursiveSync(rootDir, tempDir);

// Ensure an empty logs directory exists in the package
const logDir = path.join(tempDir, 'logs');
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir);
}
// Create a placeholder .gitkeep in logs directory
fs.writeFileSync(path.join(logDir, '.gitkeep'), '');

console.log('🤐 Compressing into pospharma-web.zip...');
try {
  // Use PowerShell Compress-Archive for native Windows ZIP generation
  // We use powershell command so it's fully native and robust on Windows.
  const zipCmd = `powershell -Command "Compress-Archive -Path '${tempDir}\\*' -DestinationPath '${zipFile}' -Force"`;
  execSync(zipCmd, { stdio: 'inherit' });
  console.log('✨ Compression completed successfully!');
} catch (err) {
  console.error('❌ Compression failed. Attempting fallback using tar...');
  try {
    // Fallback to tar.exe which is available in Windows 10+
    execSync(`tar -a -c -f "${zipFile}" -C "${tempDir}" .`, { stdio: 'inherit' });
    console.log('✨ Compression completed using tar fallback!');
  } catch (tarErr) {
    console.error('❌ Fallback compression failed:', tarErr.message);
    process.exit(1);
  }
}

console.log('🧹 Cleaning up temporary files...');
fs.rmSync(tempDir, { recursive: true, force: true });

console.log(`\n🎉 Web package created successfully at: \n👉 ${zipFile}\n`);
