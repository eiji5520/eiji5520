=== AI自動投稿 ===
Contributors: ai-autopost
Tags: openai, chatgpt, auto post, content generation, ai
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ChatGPT APIを使用してトラブル解決記事を自動生成し、WordPressへ投稿するプラグイン。シンレンタルサーバー対応。

== Description ==

AI自動投稿は、OpenAI（ChatGPT）のAPIを使用して、日本語のトラブル解決記事を自動生成するWordPressプラグインです。

**主な機能**

* OpenAI API（ChatGPT）による記事自動生成
* 複数キーワードの一括登録とキュー管理
* WP-Cron + サーバーcron併用による安定した自動実行
* シンレンタルサーバー対応のcron-trigger機能
* 重複投稿の自動検出とスキップ
* 詳細なログ機能

**生成される記事の特徴**

* 800〜1200文字の日本語記事
* トラブル解決に特化した構成
* 見出し: まず最初にやること → よくある原因 → 対処法 → FAQ → まとめ
* 初心者にもわかりやすい表現

**シンレンタルサーバーでの安定動作**

シンレンタルサーバー（Xserver系）では、アクセスが無いとWP-Cronが動作しないことがあります。
このプラグインは、トークン認証付きのcron-triggerを内蔵しており、サーバーcronから確実に起動できます。

== Installation ==

1. プラグインファイルを `/wp-content/plugins/ai-autopost` ディレクトリにアップロードします
2. WordPress管理画面の「プラグイン」メニューから「AI自動投稿」を有効化します
3. 「AI自動投稿」メニューから設定を行います
4. OpenAI APIキーを入力します
5. キーワードをキューに追加して、テスト生成を実行します

**シンレンタルサーバーでのcron設定**

1. サーバーパネルにログイン
2. 「cron設定」を開く
3. 管理画面の「cron設定」タブに表示されているcurlコマンドを登録
4. cron式を設定（例: */5 * * * *）

== Frequently Asked Questions ==

= APIキーはどこで取得できますか？ =

OpenAIの公式サイト（https://platform.openai.com/api-keys）でAPIキーを取得できます。

= 記事が生成されません =

以下を確認してください：
* APIキーが正しく設定されているか
* キューにキーワードが登録されているか
* ログタブでエラーメッセージを確認

= 同じキーワードで何度も生成されてしまう =

このプラグインは自動で重複チェックを行います。
同一キーワード・同一タイトルの記事は自動でスキップされます。

= cron-triggerが403エラーになる =

トークンが正しいか確認してください。
トークンを再生成した場合は、サーバーcronの設定も更新が必要です。

== Changelog ==

= 1.0.0 =
* 初回リリース
* OpenAI API連携による記事自動生成
* キュー管理機能
* WP-Cron + サーバーcron対応
* シンレンタルサーバー向けcron-trigger実装
* 重複チェック機能
* ログ機能

== Upgrade Notice ==

= 1.0.0 =
初回リリースです。

== Screenshots ==

1. 設定画面
2. キュー/手動生成画面
3. ログ画面
4. cron設定画面

== 開発者向け情報 ==

**ファイル構成**

```
ai-autopost/
├── ai-autopost.php      # メインプラグインファイル
├── includes/
│   ├── admin.php        # 管理画面
│   ├── settings.php     # 設定管理
│   ├── generator.php    # 記事生成
│   ├── cron.php         # Cron処理
│   ├── logger.php       # ログ機能
│   └── cron-trigger.php # 外部cronトリガー
├── assets/
│   └── admin.css        # 管理画面スタイル
└── readme.txt           # このファイル
```

**フック**

* `ai_autopost_cron_event` - スケジュール実行時に発火

**オプション**

* `ai_autopost_settings` - プラグイン設定
* `ai_autopost_queue` - キーワードキュー
* `ai_autopost_logs` - ログデータ
