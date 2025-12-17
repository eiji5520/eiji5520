# Sudoku Android App

数独アプリ - AdMobバナー広告 + 広告削除課金（非消費型）対応

## 技術スタック

- Vite + React + TypeScript
- Capacitor (Android)
- @capacitor-community/admob (広告)
- cordova-plugin-purchase (アプリ内課金)

## ファイル構成

```
/
├── src/
│   ├── components/
│   │   ├── SudokuBoard.tsx      # 9x9盤面コンポーネント
│   │   ├── SudokuBoard.css
│   │   ├── Cell.tsx             # 個別セルコンポーネント
│   │   ├── Cell.css
│   │   ├── Controls.tsx         # 数字入力・リセット・次の問題
│   │   ├── Controls.css
│   │   ├── Settings.tsx         # 設定画面（広告削除購入）
│   │   ├── Settings.css
│   │   └── BannerAd.tsx         # バナー広告表示制御
│   ├── services/
│   │   ├── AdService.ts         # AdMobラッパー
│   │   └── PurchaseService.ts   # IAP（課金）ラッパー
│   ├── hooks/
│   │   ├── useSudoku.ts         # 数独ゲームロジック
│   │   └── usePurchase.ts       # 課金状態管理
│   ├── types/
│   │   └── index.ts             # 型定義
│   ├── App.tsx
│   ├── App.css
│   ├── main.tsx
│   └── index.css
├── public/
│   └── puzzles/
│       └── easy.json            # パズルデータ
├── android/                     # Capacitor Android プロジェクト
├── .env.development             # 開発用環境変数（テスト広告ID）
├── .env.production              # 本番用環境変数（本番広告ID）
├── capacitor.config.ts
├── package.json
└── README.md
```

## セットアップ

### 1. 依存関係のインストール

```bash
npm install
```

### 2. 開発サーバー起動（Web版）

```bash
npm run dev
```

### 3. Android向けビルド＆同期

```bash
npm run cap:sync
```

### 4. Android Studioで開く

```bash
npm run cap:open
```

## Android Studio での実行手順

### 初回セットアップ

1. Android Studio で `android` フォルダを開く
2. Gradle Sync が完了するまで待機
3. AVD Manager から Android エミュレータを作成（または実機を接続）
4. 「Run」ボタン（▶）をクリックして実行

### 実機デバッグ（推奨）

広告と課金のテストには実機を使用することを推奨：

1. Android端末の「開発者向けオプション」を有効化
2. 「USBデバッグ」をON
3. PCにUSB接続し、デバッグを許可
4. Android Studio でデバイスを選択して実行

## 広告設定

### テスト用広告ID（開発時）

`.env.development` に設定済み：
```
VITE_ADMOB_APP_ID=ca-app-pub-3940256099942544~3347511713
VITE_ADMOB_BANNER_ID=ca-app-pub-3940256099942544/6300978111
```

### 本番用広告ID

1. [AdMob Console](https://apps.admob.com/) でアプリを登録
2. バナー広告ユニットを作成
3. `.env.production` に ID を設定：
```
VITE_ADMOB_APP_ID=ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX
VITE_ADMOB_BANNER_ID=ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX
```

4. `android/app/src/main/AndroidManifest.xml` の AdMob App ID を更新：
```xml
<meta-data
    android:name="com.google.android.gms.ads.APPLICATION_ID"
    android:value="ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX"/>
```

## 課金（IAP）設定

### Google Play Console での設定

1. Play Console でアプリを作成
2. 「収益化」→「アプリ内アイテム」
3. 「非消費型アイテム」として `remove_ads` を作成
   - 商品ID: `remove_ads`
   - 価格: 任意（例: ¥250）
   - タイトル: 「広告を削除」
   - 説明: 「広告を永久に削除します」

### テスト方法

**重要: 課金テストには「内部テスト」トラックが必要**

1. Play Console で「内部テスト」トラックにAABをアップロード
2. テスターのメールアドレスを登録
3. テスターは Play ストアのオプトインリンクからアプリをインストール
4. テスター用のGoogleアカウントで課金をテスト

ライセンステスター設定:
- Play Console →「設定」→「ライセンステスト」
- テスト用Googleアカウントを追加
- レスポンス設定: 「LICENSED」

## AAB生成手順（リリースビルド）

### 1. Web アセットをビルド

```bash
npm run build
```

### 2. Capacitor 同期

```bash
npx cap sync android
```

### 3. Android Studio でAAB生成

1. Android Studio で `android` フォルダを開く
2. メニュー: `Build` → `Generate Signed Bundle / APK...`
3. `Android App Bundle` を選択
4. Keystore 設定:
   - 新規作成の場合: `Create new...`
   - Key store path: 任意の場所に `.jks` ファイルを保存
   - パスワード、エイリアス等を設定（**忘れないよう保管**）
5. `release` ビルドタイプを選択
6. `Finish` で AAB が生成される

生成場所: `android/app/release/app-release.aab`

### Play App Signing（推奨）

Google Play に初回アップロード時、「Play App Signing」を有効化：
- Googleがアプリ署名鍵を管理
- アップロード鍵を紛失してもアプリ更新可能
- セキュリティ向上

## バージョン管理

### versionCode / versionName 更新

`android/app/build.gradle`:
```gradle
defaultConfig {
    versionCode 1      // 整数。アップデート毎に +1
    versionName "1.0"  // ユーザー向け表示バージョン
}
```

**注意**: Play Console へのアップロード毎に versionCode を増加させる必要あり

## ストア提出要件

### プライバシーポリシー

AdMob使用のため、プライバシーポリシーが**必須**。
以下の内容を含める：
- 広告配信にGoogle AdMobを使用
- AdMobが広告IDを収集・使用
- 個人情報の第三者提供について

### Data Safety 申告

Play Console の「Data Safety」セクションで申告：

| 項目 | 設定 |
|------|------|
| 位置情報 | 収集しない |
| 個人情報 | 収集しない |
| 財務情報 | 収集しない（※IAP購入はGoogleが処理） |
| デバイスID | **収集する**（AdMobが使用） |
| 広告ID | **収集する**（AdMobが使用） |

**「広告」目的でデバイスID/広告IDを収集** と申告

### 必要なアセット

| 項目 | サイズ |
|------|--------|
| アプリアイコン | 512x512 PNG |
| フィーチャーグラフィック | 1024x500 PNG |
| スクリーンショット | 電話用 2枚以上 |

## つまずきやすいポイント

### 広告関連

1. **AdMob App ID 未設定**
   - AndroidManifest.xml に `APPLICATION_ID` を正しく設定
   - アプリIDと広告ユニットIDを混同しない

2. **テスト広告が表示されない**
   - 実機またはエミュレータで確認
   - ネットワーク接続を確認
   - `isTesting: true` が設定されているか確認

3. **本番広告が表示されない**
   - AdMob審査完了まで数時間〜1日かかる
   - アカウント停止されていないか確認

### 課金関連

1. **購入ボタンが反応しない**
   - 内部テストトラックにAABがアップロードされているか確認
   - テスターとして登録されているか確認
   - オプトインリンクからインストールしたか確認

2. **購入が完了しない**
   - ライセンステスターとして登録されているか確認
   - Play Storeが最新か確認
   - 端末のGoogleアカウントがテスターと一致しているか確認

3. **購入復元が機能しない**
   - 同じGoogleアカウントでログインしているか確認
   - Play Store のキャッシュをクリアしてみる

### ビルド関連

1. **Gradle Sync 失敗**
   - Android Studio を最新版に更新
   - Gradle キャッシュをクリア: `File` → `Invalidate Caches`

2. **署名鍵の紛失**
   - Play App Signing を使っていれば、アップロード鍵の再設定が可能
   - 使っていない場合、新しいアプリとして再登録が必要

3. **targetSdk 関連エラー**
   - `android/variables.gradle` で targetSdkVersion を確認
   - 現在は API 35 (Android 15) を設定

## アイコン・スプラッシュスクリーン

### アイコンの差し替え

1. アイコン画像を用意（1024x1024 推奨）
2. [Android Asset Studio](https://romannurik.github.io/AndroidAssetStudio/) でリサイズ
3. 生成されたファイルを以下に配置:
   - `android/app/src/main/res/mipmap-hdpi/`
   - `android/app/src/main/res/mipmap-mdpi/`
   - `android/app/src/main/res/mipmap-xhdpi/`
   - `android/app/src/main/res/mipmap-xxhdpi/`
   - `android/app/src/main/res/mipmap-xxxhdpi/`

### スプラッシュスクリーン

Capacitorはデフォルトでスプラッシュスクリーンをサポート:
- `android/app/src/main/res/drawable/splash.png` を差し替え
- `android/app/src/main/res/values/styles.xml` でカスタマイズ可能

## コマンドまとめ

```bash
# 開発サーバー起動
npm run dev

# 本番ビルド
npm run build

# Android同期
npm run cap:sync

# Android Studio で開く
npm run cap:open

# Android実行
npm run cap:run
```

## ライセンス

MIT
