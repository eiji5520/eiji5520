<?php
/**
 * 管理画面
 *
 * @package AI_AutoPost
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 管理画面クラス
 */
class AI_AutoPost_Admin {

    /**
     * メニュースラッグ
     */
    const MENU_SLUG = 'ai-autopost';

    /**
     * インスタンス
     *
     * @var AI_AutoPost_Admin
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @return AI_AutoPost_Admin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
    }

    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'AI自動投稿',
            'AI自動投稿',
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_admin_page' ),
            'dashicons-edit-page',
            30
        );
    }

    /**
     * 管理画面用アセットを読み込み
     *
     * @param string $hook フック名
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ai-autopost-admin',
            AI_AUTOPOST_PLUGIN_URL . 'assets/admin.css',
            array(),
            AI_AUTOPOST_VERSION
        );
    }

    /**
     * 管理画面アクションを処理
     */
    public function handle_admin_actions() {
        // 権限チェック
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // キュー追加
        if ( isset( $_POST['ai_autopost_add_queue'] ) ) {
            $this->handle_add_queue();
        }

        // 今すぐ生成
        if ( isset( $_POST['ai_autopost_generate_now'] ) ) {
            $this->handle_generate_now();
        }

        // キュークリア
        if ( isset( $_POST['ai_autopost_clear_queue'] ) ) {
            $this->handle_clear_queue();
        }

        // ログクリア
        if ( isset( $_POST['ai_autopost_clear_logs'] ) ) {
            $this->handle_clear_logs();
        }

        // トークン再生成
        if ( isset( $_POST['ai_autopost_regenerate_token'] ) ) {
            $this->handle_regenerate_token();
        }
    }

    /**
     * キュー追加を処理
     */
    private function handle_add_queue() {
        // nonceチェック
        if ( ! isset( $_POST['ai_autopost_queue_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_autopost_queue_nonce'] ) ), 'ai_autopost_add_queue' ) ) {
            add_settings_error( 'ai_autopost', 'nonce_error', 'セキュリティチェックに失敗しました', 'error' );
            return;
        }

        if ( empty( $_POST['keywords'] ) ) {
            add_settings_error( 'ai_autopost', 'empty_keywords', 'キーワードを入力してください', 'error' );
            return;
        }

        $keywords_text = sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) );
        $keywords      = array_filter( array_map( 'trim', explode( "\n", $keywords_text ) ) );

        if ( empty( $keywords ) ) {
            add_settings_error( 'ai_autopost', 'empty_keywords', '有効なキーワードがありません', 'error' );
            return;
        }

        $added = AI_AutoPost_Generator::add_to_queue( $keywords );

        add_settings_error(
            'ai_autopost',
            'queue_added',
            sprintf( '%d件のキーワードをキューに追加しました', $added ),
            'success'
        );
    }

    /**
     * 今すぐ生成を処理
     */
    private function handle_generate_now() {
        // nonceチェック
        if ( ! isset( $_POST['ai_autopost_generate_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_autopost_generate_nonce'] ) ), 'ai_autopost_generate_now' ) ) {
            add_settings_error( 'ai_autopost', 'nonce_error', 'セキュリティチェックに失敗しました', 'error' );
            return;
        }

        // ステータス取得
        $post_status = isset( $_POST['generate_status'] ) && 'publish' === $_POST['generate_status']
            ? 'publish'
            : 'draft';

        // キューから取得
        $keyword = AI_AutoPost_Generator::get_from_queue();

        if ( null === $keyword ) {
            add_settings_error( 'ai_autopost', 'empty_queue', 'キューが空です。キーワードを追加してください。', 'error' );
            return;
        }

        // 生成実行
        $result = AI_AutoPost_Generator::generate_and_post( $keyword, $post_status );

        if ( $result['success'] ) {
            AI_AutoPost_Generator::remove_from_queue();
            add_settings_error(
                'ai_autopost',
                'generate_success',
                sprintf( '記事を生成しました：%s（投稿ID: %d）', esc_html( $keyword ), $result['post_id'] ),
                'success'
            );
        } elseif ( ! empty( $result['skipped'] ) ) {
            AI_AutoPost_Generator::remove_from_queue();
            add_settings_error( 'ai_autopost', 'generate_skipped', $result['message'], 'warning' );
        } else {
            AI_AutoPost_Generator::move_to_queue_end( $keyword );
            add_settings_error( 'ai_autopost', 'generate_error', $result['message'], 'error' );
        }
    }

    /**
     * キュークリアを処理
     */
    private function handle_clear_queue() {
        // nonceチェック
        if ( ! isset( $_POST['ai_autopost_clear_queue_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_autopost_clear_queue_nonce'] ) ), 'ai_autopost_clear_queue' ) ) {
            add_settings_error( 'ai_autopost', 'nonce_error', 'セキュリティチェックに失敗しました', 'error' );
            return;
        }

        AI_AutoPost_Generator::clear_queue();
        add_settings_error( 'ai_autopost', 'queue_cleared', 'キューをクリアしました', 'success' );
    }

    /**
     * ログクリアを処理
     */
    private function handle_clear_logs() {
        // nonceチェック
        if ( ! isset( $_POST['ai_autopost_clear_logs_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_autopost_clear_logs_nonce'] ) ), 'ai_autopost_clear_logs' ) ) {
            add_settings_error( 'ai_autopost', 'nonce_error', 'セキュリティチェックに失敗しました', 'error' );
            return;
        }

        AI_AutoPost_Logger::clear();
        add_settings_error( 'ai_autopost', 'logs_cleared', 'ログをクリアしました', 'success' );
    }

    /**
     * トークン再生成を処理
     */
    private function handle_regenerate_token() {
        // nonceチェック
        if ( ! isset( $_POST['ai_autopost_token_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_autopost_token_nonce'] ) ), 'ai_autopost_regenerate_token' ) ) {
            add_settings_error( 'ai_autopost', 'nonce_error', 'セキュリティチェックに失敗しました', 'error' );
            return;
        }

        $new_token = wp_generate_password( 32, false );
        AI_AutoPost_Settings::update( 'cron_token', $new_token );
        add_settings_error( 'ai_autopost', 'token_regenerated', 'cronトークンを再生成しました。サーバーcronの設定を更新してください。', 'success' );
    }

    /**
     * 管理画面を描画
     */
    public function render_admin_page() {
        // 権限チェック
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'アクセス権限がありません' );
        }

        // 現在のタブを取得
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
        $tabs        = array(
            'settings' => '設定',
            'queue'    => 'キュー/手動生成',
            'logs'     => 'ログ',
            'cron'     => 'cron設定',
        );

        ?>
        <div class="wrap ai-autopost-admin">
            <h1>AI自動投稿</h1>

            <?php settings_errors( 'ai_autopost' ); ?>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $tab_key ) ); ?>"
                       class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="ai-autopost-tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'queue':
                        $this->render_queue_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'cron':
                        $this->render_cron_tab();
                        break;
                    default:
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 設定タブを描画
     */
    private function render_settings_tab() {
        $settings = AI_AutoPost_Settings::get_all();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( AI_AutoPost_Settings::OPTION_GROUP ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_key">OpenAI API Key <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="password"
                               name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[api_key]"
                               id="api_key"
                               class="regular-text"
                               value=""
                               placeholder="<?php echo esc_attr( AI_AutoPost_Settings::mask_api_key( $settings['api_key'] ) ); ?>"
                               autocomplete="new-password">
                        <p class="description">
                            <?php if ( ! empty( $settings['api_key'] ) ) : ?>
                                現在のキー: <?php echo esc_html( AI_AutoPost_Settings::mask_api_key( $settings['api_key'] ) ); ?><br>
                                新しいキーを入力すると上書きされます。空欄のまま保存すると現在のキーが維持されます。
                            <?php else : ?>
                                OpenAI APIキーを入力してください（sk-で始まる文字列）
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="model">モデル名</label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[model]" id="model">
                            <?php
                            $models = array(
                                'gpt-4.1-mini'   => 'gpt-4.1-mini（推奨）',
                                'gpt-4.1'        => 'gpt-4.1',
                                'gpt-4o'         => 'gpt-4o',
                                'gpt-4o-mini'    => 'gpt-4o-mini',
                                'gpt-4-turbo'    => 'gpt-4-turbo',
                                'gpt-3.5-turbo'  => 'gpt-3.5-turbo',
                            );
                            foreach ( $models as $value => $label ) :
                                ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['model'], $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="temperature">Temperature</label>
                    </th>
                    <td>
                        <input type="number"
                               name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[temperature]"
                               id="temperature"
                               value="<?php echo esc_attr( $settings['temperature'] ); ?>"
                               min="0"
                               max="2"
                               step="0.1"
                               class="small-text">
                        <p class="description">0.0〜2.0の値。高いほど創造的、低いほど一貫性のある文章になります（推奨: 0.7）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="post_status">投稿ステータス</label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[post_status]" id="post_status">
                            <option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>>下書き</option>
                            <option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>>公開</option>
                        </select>
                        <p class="description">自動生成時のデフォルト投稿ステータス</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="category_id">カテゴリID</label>
                    </th>
                    <td>
                        <input type="number"
                               name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[category_id]"
                               id="category_id"
                               value="<?php echo esc_attr( $settings['category_id'] ); ?>"
                               min="0"
                               class="small-text">
                        <p class="description">投稿するカテゴリのID（0または空欄で未分類）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="device">想定デバイス</label>
                    </th>
                    <td>
                        <input type="text"
                               name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[device]"
                               id="device"
                               value="<?php echo esc_attr( $settings['device'] ); ?>"
                               class="regular-text">
                        <p class="description">記事の想定読者が使用するデバイス（例: Android/iPhone）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="situation">想定状況</label>
                    </th>
                    <td>
                        <textarea name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[situation]"
                                  id="situation"
                                  rows="2"
                                  class="large-text"><?php echo esc_textarea( $settings['situation'] ); ?></textarea>
                        <p class="description">記事の想定読者が置かれている状況</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">自動実行</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[auto_enabled]"
                                   value="1"
                                   <?php checked( $settings['auto_enabled'], true ); ?>>
                            自動実行を有効にする
                        </label>
                        <p class="description">チェックを入れると、指定時刻にキューから自動で記事を生成します</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="posts_per_run">1回の実行で生成する本数</label>
                    </th>
                    <td>
                        <input type="number"
                               name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[posts_per_run]"
                               id="posts_per_run"
                               value="<?php echo esc_attr( $settings['posts_per_run'] ); ?>"
                               min="1"
                               max="10"
                               class="small-text">
                        <p class="description">1〜10の間で設定（推奨: 3）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="run_time">実行時間</label>
                    </th>
                    <td>
                        <input type="time"
                               name="<?php echo esc_attr( AI_AutoPost_Settings::OPTION_NAME ); ?>[run_time]"
                               id="run_time"
                               value="<?php echo esc_attr( $settings['run_time'] ); ?>">
                        <p class="description">
                            WP-Cronイベントの基準時刻（サーバーcron使用時はこの時刻は参考程度になります）<br>
                            次回実行予定: <?php echo esc_html( AI_AutoPost_Cron::get_next_scheduled() ?: '未スケジュール' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( '設定を保存' ); ?>
        </form>
        <?php
    }

    /**
     * キュー/手動生成タブを描画
     */
    private function render_queue_tab() {
        $queue       = AI_AutoPost_Generator::get_queue( 20 );
        $queue_count = AI_AutoPost_Generator::get_queue_count();
        ?>
        <div class="ai-autopost-section">
            <h2>キーワードを追加</h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'ai_autopost_add_queue', 'ai_autopost_queue_nonce' ); ?>
                <p>
                    <label for="keywords">キーワード（1行に1つ）</label>
                </p>
                <textarea name="keywords"
                          id="keywords"
                          rows="8"
                          class="large-text"
                          placeholder="iPhone バッテリー 減りが早い&#10;Android 画面が固まる&#10;LINE 通知が来ない"></textarea>
                <p>
                    <button type="submit" name="ai_autopost_add_queue" class="button button-primary">
                        キューに追加
                    </button>
                </p>
            </form>
        </div>

        <div class="ai-autopost-section">
            <h2>現在のキュー（<?php echo esc_html( $queue_count ); ?>件）</h2>
            <?php if ( ! empty( $queue ) ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>キーワード</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $queue as $index => $keyword ) : ?>
                            <tr>
                                <td><?php echo esc_html( $index + 1 ); ?></td>
                                <td><?php echo esc_html( $keyword ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( $queue_count > 20 ) : ?>
                    <p class="description">他 <?php echo esc_html( $queue_count - 20 ); ?> 件...</p>
                <?php endif; ?>

                <form method="post" action="" style="margin-top: 15px;">
                    <?php wp_nonce_field( 'ai_autopost_clear_queue', 'ai_autopost_clear_queue_nonce' ); ?>
                    <button type="submit"
                            name="ai_autopost_clear_queue"
                            class="button"
                            onclick="return confirm('キューをすべてクリアしますか？');">
                        キューをクリア
                    </button>
                </form>
            <?php else : ?>
                <p class="description">キューは空です</p>
            <?php endif; ?>
        </div>

        <div class="ai-autopost-section">
            <h2>今すぐ生成</h2>
            <?php if ( ! AI_AutoPost_Settings::has_api_key() ) : ?>
                <div class="notice notice-warning inline">
                    <p>APIキーが設定されていません。設定タブでAPIキーを入力してください。</p>
                </div>
            <?php elseif ( empty( $queue ) ) : ?>
                <div class="notice notice-info inline">
                    <p>キューが空です。キーワードを追加してください。</p>
                </div>
            <?php else : ?>
                <p>キューの先頭「<strong><?php echo esc_html( $queue[0] ); ?></strong>」を今すぐ生成します。</p>

                <form method="post" action="" class="ai-autopost-generate-form">
                    <?php wp_nonce_field( 'ai_autopost_generate_now', 'ai_autopost_generate_nonce' ); ?>

                    <p>
                        <button type="submit"
                                name="ai_autopost_generate_now"
                                class="button button-primary">
                            今すぐ1本生成（下書き）
                        </button>
                        <input type="hidden" name="generate_status" value="draft">
                    </p>
                </form>

                <form method="post" action="" class="ai-autopost-generate-form" style="margin-top: 10px;">
                    <?php wp_nonce_field( 'ai_autopost_generate_now', 'ai_autopost_generate_nonce' ); ?>

                    <p>
                        <button type="submit"
                                name="ai_autopost_generate_now"
                                class="button button-secondary"
                                onclick="return confirm('公開ステータスで投稿します。よろしいですか？');">
                            今すぐ1本生成（公開）
                        </button>
                        <input type="hidden" name="generate_status" value="publish">
                    </p>
                    <p class="description" style="color: #d63638;">
                        注意：「公開」を選択すると、生成後すぐに記事が公開されます。
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * ログタブを描画
     */
    private function render_logs_tab() {
        $logs = AI_AutoPost_Logger::get( 30 );
        ?>
        <div class="ai-autopost-section">
            <h2>ログ（直近30件）</h2>

            <?php if ( ! empty( $logs ) ) : ?>
                <table class="widefat striped ai-autopost-logs">
                    <thead>
                        <tr>
                            <th style="width: 150px;">日時</th>
                            <th style="width: 80px;">種別</th>
                            <th>メッセージ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr class="<?php echo esc_attr( AI_AutoPost_Logger::get_type_class( $log['type'] ) ); ?>">
                                <td><?php echo esc_html( $log['timestamp'] ); ?></td>
                                <td>
                                    <span class="log-type-badge log-type-<?php echo esc_attr( $log['type'] ); ?>">
                                        <?php echo esc_html( AI_AutoPost_Logger::get_type_label( $log['type'] ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html( $log['message'] ); ?>
                                    <?php if ( ! empty( $log['data'] ) ) : ?>
                                        <details>
                                            <summary>詳細</summary>
                                            <pre><?php echo esc_html( wp_json_encode( $log['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" action="" style="margin-top: 15px;">
                    <?php wp_nonce_field( 'ai_autopost_clear_logs', 'ai_autopost_clear_logs_nonce' ); ?>
                    <button type="submit"
                            name="ai_autopost_clear_logs"
                            class="button"
                            onclick="return confirm('ログをすべてクリアしますか？');">
                        ログをクリア
                    </button>
                </form>
            <?php else : ?>
                <p class="description">ログはありません</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * cron設定タブを描画
     */
    private function render_cron_tab() {
        $settings          = AI_AutoPost_Settings::get_all();
        $cron_trigger_url  = AI_AutoPost_Settings::get_cron_trigger_url();
        ?>
        <div class="ai-autopost-section">
            <h2>サーバーcron設定（シンレンタルサーバー向け）</h2>

            <div class="notice notice-info inline">
                <p>
                    <strong>重要：</strong>シンレンタルサーバーでは、アクセスが無いとWP-Cronが動作しないことがあります。<br>
                    サーバーcronを使用すると、アクセスが無くても確実に記事を自動生成できます。
                </p>
            </div>

            <h3>cronトークン</h3>
            <p>
                <code><?php echo esc_html( $settings['cron_token'] ); ?></code>
            </p>
            <form method="post" action="">
                <?php wp_nonce_field( 'ai_autopost_regenerate_token', 'ai_autopost_token_nonce' ); ?>
                <button type="submit"
                        name="ai_autopost_regenerate_token"
                        class="button"
                        onclick="return confirm('トークンを再生成すると、現在のcron設定を更新する必要があります。続行しますか？');">
                    トークンを再生成
                </button>
            </form>

            <h3 style="margin-top: 30px;">cron-trigger URL</h3>
            <p>以下のURLをサーバーcronから定期的に呼び出してください。</p>
            <div class="ai-autopost-code-block">
                <code><?php echo esc_url( $cron_trigger_url ); ?></code>
            </div>

            <h3 style="margin-top: 30px;">推奨curlコマンド</h3>
            <p>シンレンタルサーバーのcron設定で使用するコマンドです。</p>
            <div class="ai-autopost-code-block">
                <code>curl -A "Mozilla/5.0" -s "<?php echo esc_url( $cron_trigger_url ); ?>" >/dev/null 2>&1</code>
            </div>

            <h3 style="margin-top: 30px;">cron式（実行間隔）</h3>
            <table class="widefat" style="max-width: 500px;">
                <thead>
                    <tr>
                        <th>cron式</th>
                        <th>説明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>*/5 * * * *</code></td>
                        <td>5分ごとに実行（推奨）</td>
                    </tr>
                    <tr>
                        <td><code>0 9 * * *</code></td>
                        <td>毎日9:00に実行</td>
                    </tr>
                    <tr>
                        <td><code>0 9,21 * * *</code></td>
                        <td>毎日9:00と21:00に実行</td>
                    </tr>
                    <tr>
                        <td><code>0 */6 * * *</code></td>
                        <td>6時間ごとに実行</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top: 30px;">設定手順（シンレンタルサーバー）</h3>
            <ol class="ai-autopost-steps">
                <li>シンレンタルサーバーのサーバーパネルにログインします</li>
                <li>「cron設定」または「cronジョブ」メニューを開きます</li>
                <li>「cronジョブを追加」をクリックします</li>
                <li>以下を設定します：
                    <ul>
                        <li><strong>分：</strong><code>*/5</code>（5分ごとの場合）</li>
                        <li><strong>時：</strong><code>*</code></li>
                        <li><strong>日：</strong><code>*</code></li>
                        <li><strong>月：</strong><code>*</code></li>
                        <li><strong>曜日：</strong><code>*</code></li>
                        <li><strong>コマンド：</strong>上記のcurlコマンドをコピー</li>
                    </ul>
                </li>
                <li>設定を保存します</li>
            </ol>

            <h3 style="margin-top: 30px;">より安定した動作のために（任意）</h3>
            <p>
                wp-config.php に以下の行を追加すると、WP-Cronを無効化してサーバーcronのみで動作させることができます。
                これにより、ページアクセス時の余計な処理が減り、パフォーマンスが向上します。
            </p>
            <div class="ai-autopost-code-block">
                <code>define('DISABLE_WP_CRON', true);</code>
            </div>
            <p class="description">
                ※この設定を行った場合、他のプラグインのスケジュールタスクもサーバーcron経由で実行する必要があります。
            </p>
        </div>

        <div class="ai-autopost-section">
            <h2>WP-Cron状態</h2>
            <table class="widefat" style="max-width: 400px;">
                <tr>
                    <th>スケジュール状態</th>
                    <td>
                        <?php if ( AI_AutoPost_Cron::is_scheduled() ) : ?>
                            <span style="color: green;">スケジュール済み</span>
                        <?php else : ?>
                            <span style="color: red;">未スケジュール</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>次回実行予定</th>
                    <td><?php echo esc_html( AI_AutoPost_Cron::get_next_scheduled() ?: '---' ); ?></td>
                </tr>
                <tr>
                    <th>自動実行</th>
                    <td>
                        <?php if ( $settings['auto_enabled'] ) : ?>
                            <span style="color: green;">有効</span>
                        <?php else : ?>
                            <span style="color: gray;">無効</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>DISABLE_WP_CRON</th>
                    <td>
                        <?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
                            <span>true（WP-Cron無効）</span>
                        <?php else : ?>
                            <span>false（WP-Cron有効）</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
