# XBoard - アーキテクチャ設計書

## 高レベルアーキテクチャ

```
┌─────────────────────────────────────────────────────────────────────┐
│                        XBoard Desktop App                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────┐    IPC (contextBridge)    ┌──────────────────┐   │
│  │  Renderer     │◄──────────────────────►│  Main Process     │   │
│  │  (React UI)   │                          │  (Node.js)        │   │
│  │               │                          │                    │   │
│  │  ┌──────────┐ │                          │  ┌──────────────┐ │   │
│  │  │ Zustand  │ │                          │  │ IPC Handlers │ │   │
│  │  │ Store    │ │                          │  └──────┬───────┘ │   │
│  │  └──────────┘ │                          │         │          │   │
│  │               │                          │  ┌──────▼───────┐ │   │
│  │  ┌──────────┐ │                          │  │   Database   │ │   │
│  │  │ Pages &  │ │                          │  │  (SQLite)    │ │   │
│  │  │Components│ │                          │  └──────────────┘ │   │
│  │  └──────────┘ │                          │                    │   │
│  └──────────────┘                          │  ┌──────────────┐ │   │
│                                              │  │   Crypto     │ │   │
│  ┌──────────────────────────────────────┐  │  │  (AES-256)   │ │   │
│  │  Browser Sessions (per account)       │  │  └──────────────┘ │   │
│  │  ┌────────┐ ┌────────┐ ┌────────┐   │  │                    │   │
│  │  │Session1│ │Session2│ │Session3│   │  │  ┌──────────────┐ │   │
│  │  │(proxy) │ │(proxy) │ │(direct)│   │  │  │ Background   │ │   │
│  │  │Cookie分│ │Cookie分│ │Cookie分│   │  │  │ Tasks        │ │   │
│  │  │離      │ │離      │ │離      │   │  │  │ - 状態監視   │ │   │
│  │  └────────┘ └────────┘ └────────┘   │  │  │ - 通知取得   │ │   │
│  └──────────────────────────────────────┘  │  │ - バンチェック│ │   │
│                                              │  └──────────────┘ │   │
│                                              │                    │   │
│                                              │  ┌──────────────┐ │   │
│                                              │  │ CSV Handler  │ │   │
│                                              │  │ Bulk Ops     │ │   │
│                                              │  └──────────────┘ │   │
│                                              └──────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

## 技術スタック

| レイヤー | 技術 | 理由 |
|---------|------|------|
| GUI フレームワーク | Electron 40+ | クロスプラットフォーム、BrowserWindow によるセッション分離 |
| フロントエンド | React 19 + TypeScript | 型安全、コンポーネント指向 |
| 状態管理 | Zustand | 軽量、シンプルなAPI |
| ローカルDB | better-sqlite3 | 高速、WALモード対応、数千レコード対応 |
| 暗号化 | Node.js crypto (AES-256-GCM) | 標準ライブラリ、追加依存なし |
| CSV処理 | PapaParse | ストリーミング対応、大規模CSV対応 |
| ビルド | webpack + ts-loader | Electron main/renderer 双方に対応 |
| パッケージング | electron-builder | Windows/macOS 両対応 |

## ディレクトリ構成

```
src/
├── main/                      # Electron メインプロセス
│   ├── index.ts               # エントリーポイント、アプリライフサイクル
│   ├── preload.ts             # Preload: renderer への安全なAPI公開
│   ├── ipc-handlers.ts        # IPCハンドラー一括登録
│   ├── database.ts            # SQLite CRUD操作
│   ├── crypto.ts              # AES-256-GCM 暗号化/復号化
│   ├── session-manager.ts     # アカウント別ブラウザセッション管理
│   ├── bulk-operations.ts     # 一括操作エンジン
│   ├── background-tasks.ts    # バックグラウンド監視タスク
│   └── csv-handler.ts         # CSVインポート/エクスポート
├── renderer/                  # React フロントエンド
│   ├── index.tsx              # React エントリーポイント
│   ├── index.html             # HTML テンプレート
│   ├── App.tsx                # ルートコンポーネント
│   ├── types.d.ts             # Window API 型宣言
│   ├── store/
│   │   └── index.ts           # Zustand ストア定義
│   ├── styles/
│   │   └── global.css         # グローバルスタイル
│   ├── components/
│   │   └── sidebar/
│   │       ├── Sidebar.tsx    # サイドバーコンポーネント
│   │       └── Sidebar.css    # サイドバースタイル
│   └── pages/
│       ├── AccountsPage.tsx   # アカウント管理ページ
│       ├── BulkOperationsPage.tsx  # 一括操作ページ
│       ├── BanCheckPage.tsx   # バンチェックページ
│       ├── NotificationsPage.tsx   # 通知・DMページ
│       ├── AnalysisPage.tsx   # データ分析ページ
│       ├── SettingsPage.tsx   # 設定ページ
│       └── LogsPage.tsx       # 操作ログページ
└── shared/                    # 共有モジュール
    ├── types.ts               # 型定義
    └── constants.ts           # 定数
```

## セッション分離の仕組み

各アカウントは `session.fromPartition('persist:account-<id>')` で個別の
Electronセッションを割り当てられます。これにより:

- Cookie が完全に分離される
- プロキシをアカウント単位で設定可能
- User-Agent も個別設定可能
- 1つのアカウントの操作が他に影響しない

## セキュリティ設計

1. **暗号化**: Cookie、認証トークン、プロキシパスワードは AES-256-GCM で暗号化
2. **マスターキー**: `userData` ディレクトリに `0600` パーミッションで保存
3. **contextIsolation**: renderer からメインプロセスへは contextBridge 経由のみ
4. **sandbox**: アカウントセッションウィンドウは sandbox モード
5. **CSP**: Content-Security-Policy でスクリプト実行を制限

## データフロー

```
[ユーザー操作] → [React Component] → [Zustand Store]
    → [window.xboard.*] (IPC invoke) → [ipcMain.handle]
    → [database / session-manager / crypto]
    → [結果を返す] → [Store 更新] → [UI 再レンダリング]
```
