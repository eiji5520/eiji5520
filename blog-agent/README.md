# Blog Agent - WordPress AI記事生成プラグイン

Blog Agentは、OpenAI ChatGPTまたはGoogle Gemini APIを使用してブログ記事を自動生成するWordPressプラグインです。品質管理（QA）と承認フローを備え、安全な自動投稿機能を提供します。

## 要件

- WordPress 6.0以上
- PHP 8.0以上
- OpenSSL拡張（APIキー暗号化用）
- ZipArchive拡張（エクスポート機能用）
- OpenAI または Google Gemini API キー

## インストール

1. `blog-agent` フォルダをWordPressの `wp-content/plugins/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」から「Blog Agent」を有効化
3. 「Blog Agent」→「Settings」でAIプロバイダーとAPIキーを設定

## 初期設定

### AI設定

1. **Blog Agent** → **Settings** に移動
2. **Provider** で使用するAIを選択（OpenAI または Gemini）
3. 対応するAPIキーを入力（暗号化されて保存されます）
4. モデル、Temperature、Max Tokensを必要に応じて調整
5. **Test Connection** ボタンで接続を確認

### 自動投稿設定（重要）

自動投稿機能はデフォルトで **無効** です。有効にする前に以下を確認してください：

1. **Enable Auto-Post**: チェックを入れると自動投稿が有効
2. **Auto-Post Mode**:
   - `Draft Only`: 下書きのまま保持（最も安全）
   - `Schedule`: 予約投稿として設定
   - `Publish`: 即時公開
3. **Auto-Post Condition**:
   - `QA Pass Only`: QAに合格した記事を自動投稿
   - `QA Pass + Manual Approval`（推奨）: QA合格かつ手動承認された記事のみ
4. **Daily Post Limit**: 1日の投稿上限（3/5/10/20/30）
5. **Minimum Post Interval**: 投稿間隔（30/60/120/180分）

## 使い方

### 1. プロジェクト作成

1. **Blog Agent** → **Projects** → **Add New**
2. プロジェクト名を入力
3. 以下の情報を設定：
   - **Niche/Topic**: 記事のテーマ（例：「Web開発」）
   - **Target Persona**: ターゲット読者の説明
   - **Search Intent**: 検索意図（Informational/Commercial等）
   - **Keywords**: 含めるキーワード
   - **Tone/Style**: 文体（Professional/Casual等）
   - **Minimum Words**: 最低文字数
   - **Number of Articles**: 生成する記事数
4. **Generate Plan** をクリックしてプランを生成

### 2. 記事生成

1. **Blog Agent** → **Generate** に移動
2. プロジェクトを選択
3. 生成したい記事にチェック
4. **Queue Selected** または **Generate Selected Now** をクリック
5. 生成完了後、記事は下書きとして作成されます

### 3. 品質チェック（QA）

生成された記事は自動的にQAチェックされます：

- **文字数チェック**: 最低文字数を満たしているか
- **禁止語チェック**: 設定した禁止語が含まれていないか
- **断定表現チェック**: 「必ず」「確実に」等の断定表現
- **類似度チェック**: 既存記事との重複

**QA Status**:
- `Passed`: すべてのチェックに合格
- `Warning`: 軽微な警告あり（自動投稿可能）
- `Failed`: 重大な問題あり（自動投稿不可）

### 4. 記事承認

1. **Blog Agent** → **QA Review** に移動
2. 記事を確認し、**Approve** ボタンをクリック
3. 自動投稿条件が「QA Pass + Manual Approval」の場合、承認後に自動投稿が実行されます

### 5. エクスポート

1. **Blog Agent** → **Export** に移動
2. フォーマットを選択（CSV/Markdown/HTML）
3. フィルター条件を設定
4. **Export Articles** をクリック

## 緊急停止

自動投稿を即座に停止するには：

1. ダッシュボードまたは設定画面の **Emergency Stop** ボタンをクリック
2. 自動投稿が無効化され、保留中のジョブがすべてキャンセルされます

## トラブルシューティング

### API接続エラー

1. APIキーが正しく入力されているか確認
2. 選択したプロバイダーとAPIキーが一致しているか確認
3. APIの利用制限に達していないか確認
4. `Test Connection` ボタンで詳細なエラーメッセージを確認

### 記事が生成されない

1. ジョブキューの状態をダッシュボードで確認
2. WP-Cronが正常に動作しているか確認
3. エラーログを確認（`wp-content/debug.log`）

### 自動投稿が動作しない

1. 自動投稿が有効になっているか確認
2. 1日の投稿上限に達していないか確認
3. 投稿間隔の制限にかかっていないか確認
4. 記事がQA条件を満たしているか確認
5. 承認条件が設定されている場合、記事が承認されているか確認

### QAが厳しすぎる/緩すぎる

1. **Settings** → **QA Settings** で禁止語リストを調整
2. 類似度の閾値は現在70%に固定されています

## 安全機能

このプラグインには以下の安全機能が組み込まれています：

1. **デフォルト無効**: 自動投稿は初期状態で無効
2. **投稿上限**: 1日あたりの投稿数を制限
3. **投稿間隔**: 連続投稿を防止
4. **QAゲート**: 品質基準を満たさない記事は公開されない
5. **承認フロー**: 人間による確認を必須にできる
6. **緊急停止**: ワンクリックで全停止可能
7. **リトライ制限**: 失敗時のリトライは最大2回
8. **APIキー暗号化**: 認証情報は暗号化して保存

## 注意事項

- このプラグインはスパム目的での使用を意図していません
- 生成されたコンテンツは必ず確認してから公開してください
- APIの利用料金は使用量に応じて発生します
- 大量の記事を一度に生成するとAPI制限にかかる可能性があります

## フック

開発者向けに以下のフックが利用可能です：

```php
// 記事承認時
do_action('blog_agent_article_approved', $post_id);

// QA実行後
do_action('blog_agent_qa_completed', $post_id, $result);

// 自動投稿実行時
do_action('blog_agent_autopost_executed', $post_id, $mode);
```

## ライセンス

GPL v2 or later

## サポート

問題が発生した場合は、以下を確認してください：

1. この README ドキュメント
2. WordPress デバッグログ
3. API プロバイダーのドキュメント

## 更新履歴

### 1.0.0
- 初回リリース
- OpenAI / Gemini API対応
- プロジェクト管理機能
- 記事生成・キュー機能
- QAチェック機能
- 承認ワークフロー
- 自動投稿機能（安全設計）
- エクスポート機能（CSV/Markdown/HTML）
