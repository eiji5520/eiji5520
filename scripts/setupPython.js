const fs = require('fs');
const path = require('path');
const https = require('https');
const { spawnSync } = require('child_process');
const extract = require('extract-zip');

const PY_VERSION = '3.11.0';
const ZIP_NAME = `python-${PY_VERSION}-embed-amd64.zip`;
const DOWNLOAD_URL = `https://www.python.org/ftp/python/${PY_VERSION}/${ZIP_NAME}`;
const baseDir = path.join(__dirname, '..', 'resources', 'app', 'script');
const pyDir = path.join(baseDir, `python-${PY_VERSION}-embed-amd64`);
const zipPath = path.join(baseDir, ZIP_NAME);

async function downloadPython() {
  if (fs.existsSync(pyDir)) return;
  fs.mkdirSync(baseDir, { recursive: true });
  if (!fs.existsSync(zipPath)) {
    console.log('Downloading Python...');
    await new Promise((resolve, reject) => {
      const file = fs.createWriteStream(zipPath);
      https.get(DOWNLOAD_URL, (res) => {
        res.pipe(file);
        file.on('finish', () => file.close(resolve));
      }).on('error', reject);
    });
  }
  console.log('Extracting Python...');
  await extract(zipPath, { dir: pyDir });
}

function installPip() {
  const python = path.join(pyDir, 'python.exe');
  const req = path.join(__dirname, '..', 'python', 'requirements.txt');
  const pipFile = path.join(baseDir, '.pip_installed.json');
  if (fs.existsSync(pipFile)) return;
  console.log('Installing Python packages...');
  spawnSync(python, ['-m', 'pip', 'install', '-r', req], { stdio: 'inherit' });
  const result = spawnSync(python, ['-m', 'pip', 'freeze']);
  fs.writeFileSync(pipFile, result.stdout);
}

(async () => {
  try {
    await downloadPython();
    installPip();
  } catch (err) {
    console.error('Python setup failed:', err);
  }
})();
