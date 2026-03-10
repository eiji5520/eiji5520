#!/usr/bin/env node
// ============================================================
// XBoard - アイコン生成スクリプト
// SVG からプラットフォーム別アイコンを生成
//
// 使い方:
//   1. assets/icon.svg を用意（1024x1024推奨）
//   2. npm install --save-dev electron-icon-maker
//   3. node scripts/generate-icons.js
//
// または手動で:
//   - Windows: assets/icon.ico (256x256)
//   - macOS:   assets/icon.icns (1024x1024)
//   - Linux:   assets/icons/256x256.png
// ============================================================

const fs = require('fs');
const path = require('path');

const ASSETS_DIR = path.join(__dirname, '..', 'assets');
const ICONS_DIR = path.join(ASSETS_DIR, 'icons');

// SVG プレースホルダーアイコンを生成
const svgIcon = `<?xml version="1.0" encoding="UTF-8"?>
<svg width="1024" height="1024" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#1a1a2e"/>
      <stop offset="100%" style="stop-color:#16213e"/>
    </linearGradient>
  </defs>
  <rect width="1024" height="1024" rx="200" fill="url(#bg)"/>
  <text x="512" y="580" text-anchor="middle" font-family="Arial Black, sans-serif"
        font-size="520" font-weight="900" fill="#4ECDC4">X</text>
  <text x="512" y="780" text-anchor="middle" font-family="Arial, sans-serif"
        font-size="140" font-weight="700" fill="#a0a0b0">Board</text>
</svg>`;

// ディレクトリ作成
if (!fs.existsSync(ICONS_DIR)) {
  fs.mkdirSync(ICONS_DIR, { recursive: true });
}

// SVG保存
fs.writeFileSync(path.join(ASSETS_DIR, 'icon.svg'), svgIcon);
console.log('icon.svg を生成しました');

console.log(`
=== アイコン生成手順 ===

1. オンラインツールで変換:
   - https://www.icoconverter.com/ (SVG → ICO)
   - https://cloudconvert.com/svg-to-icns (SVG → ICNS)

2. 生成したファイルを配置:
   - assets/icon.ico   (Windows用)
   - assets/icon.icns  (macOS用)
   - assets/icons/256x256.png (Linux用)

3. または electron-icon-maker を使用:
   npm install -g electron-icon-maker
   electron-icon-maker --input=assets/icon.svg --output=assets
`);
