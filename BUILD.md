# XBoard - ビルド & パッケージング手順

## 前提条件

- Node.js 20 以上
- npm 10 以上
- Windows: Visual Studio Build Tools (native module ビルド用)
- macOS: Xcode Command Line Tools

## セットアップ

```bash
# 依存パッケージをインストール
npm install

# native module (better-sqlite3) を Electron 向けにリビルド
npm run postinstall
```

## 開発

```bash
# renderer (React) の開発サーバーを起動
npm run dev:renderer

# 別ターミナルで main プロセスをビルドして起動
npm run dev:main
```

## ビルド

```bash
# main + renderer を production ビルド
npm run build

# ビルド済みアプリを起動
npm start
```

## exe / dmg 生成（パッケージング）

### Windows (.exe インストーラー + ポータブル版)

```bash
npm run dist:win
```

出力先: `release/` ディレクトリ
- `XBoard-1.0.0-win-x64.exe` — NSIS インストーラー
- `XBoard-1.0.0-portable.exe` — インストール不要のポータブル版

### macOS (.dmg)

```bash
npm run dist:mac
```

出力先: `release/` ディレクトリ
- `XBoard-1.0.0-mac-x64.dmg` — Intel Mac 用
- `XBoard-1.0.0-mac-arm64.dmg` — Apple Silicon 用

### Linux (.AppImage / .deb)

```bash
npm run dist:linux
```

### 全プラットフォーム一括

```bash
npm run dist
```

> **注意**: クロスプラットフォームビルドには制限があります。
> Windows用exeはWindows上で、macOS用dmgはmacOS上でビルドすることを推奨します。
> GitHub Actions を使えば自動で各OS向けにビルドできます。

## アイコン準備

パッケージングの前にアイコンファイルを用意してください:

```bash
# プレースホルダーSVGを生成
node scripts/generate-icons.js
```

その後、SVGから以下のファイルを作成:
- `assets/icon.ico` — Windows用 (256x256)
- `assets/icon.icns` — macOS用 (1024x1024)
- `assets/icons/256x256.png` — Linux用

## GitHub Actions でのCI/CD

`.github/workflows/build.yml` を作成すれば、push時に自動ビルド可能:

```yaml
name: Build
on:
  push:
    tags: ['v*']

jobs:
  build-windows:
    runs-on: windows-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
      - run: npm ci
      - run: npm run dist:win
      - uses: actions/upload-artifact@v4
        with:
          name: windows-build
          path: release/*.exe

  build-mac:
    runs-on: macos-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
      - run: npm ci
      - run: npm run dist:mac
      - uses: actions/upload-artifact@v4
        with:
          name: mac-build
          path: release/*.dmg
```

## トラブルシューティング

### `better-sqlite3` のビルドエラー
```bash
# Electron 用に再ビルド
npx electron-rebuild -f -w better-sqlite3
```

### Windows で NSIS が見つからない
```bash
# NSIS をインストール (choco)
choco install nsis

# または electron-builder が自動でダウンロードします
```

### macOS で署名エラー
開発用ビルドでは署名は不要です。配布時は Apple Developer Program に登録し、
環境変数 `CSC_LINK` と `CSC_KEY_PASSWORD` を設定してください。
