<?php
/**
 * Plugin Name: AI自動投稿
 * Plugin URI: https://life-helpnote.com/
 * Description: ChatGPT APIを使用してトラブル解決記事を自動生成し、WordPressへ投稿するプラグイン
 * Version: 1.0.0
 * Author: AI AutoPost
 * Author URI: https://life-helpnote.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-autopost
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグイン定数
define( 'AI_AUTOPOST_VERSION', '1.0.0' );
define( 'AI_AUTOPOST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_AUTOPOST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_AUTOPOST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * プラグインのメインクラス
 */
class AI_AutoPost {

    /**
     * インスタンス
     *
     * @var AI_AutoPost
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @return AI_AutoPost
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * 依存ファイルを読み込み
     */
    private function load_dependencies() {
        require_once AI_AUTOPOST_PLUGIN_DIR . 'includes/logger.php';
        require_once AI_AUTOPOST_PLUGIN_DIR . 'includes/settings.php';
        require_once AI_AUTOPOST_PLUGIN_DIR . 'includes/generator.php';
        require_once AI_AUTOPOST_PLUGIN_DIR . 'includes/cron.php';
        require_once AI_AUTOPOST_PLUGIN_DIR . 'includes/admin.php';
    }

    /**
     * フックを初期化
     */
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // 管理画面
        if ( is_admin() ) {
            AI_AutoPost_Admin::get_instance();
        }

        // Cron
        AI_AutoPost_Cron::get_instance();
    }

    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // デフォルト設定を保存
        $default_settings = array(
            'api_key'          => '',
            'model'            => 'gpt-4.1-mini',
            'temperature'      => 0.7,
            'post_status'      => 'draft',
            'category_id'      => 0,
            'device'           => 'Android/iPhone',
            'situation'        => 'アプリや端末で問題が起きて困っている',
            'auto_enabled'     => false,
            'posts_per_run'    => 3,
            'run_time'         => '09:10',
            'cron_token'       => wp_generate_password( 32, false ),
        );

        $existing = get_option( 'ai_autopost_settings' );
        if ( false === $existing ) {
            add_option( 'ai_autopost_settings', $default_settings );
        }

        // キューを初期化
        if ( false === get_option( 'ai_autopost_queue' ) ) {
            add_option( 'ai_autopost_queue', array() );
        }

        // ログを初期化
        if ( false === get_option( 'ai_autopost_logs' ) ) {
            add_option( 'ai_autopost_logs', array() );
        }

        // Cronスケジュールを設定
        AI_AutoPost_Cron::schedule_event();

        // パーマリンク再設定
        flush_rewrite_rules();
    }

    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // Cronイベントをクリア
        AI_AutoPost_Cron::clear_scheduled_event();
        flush_rewrite_rules();
    }

    /**
     * 設定を取得
     *
     * @param string $key 設定キー
     * @param mixed  $default デフォルト値
     * @return mixed
     */
    public static function get_setting( $key, $default = null ) {
        $settings = get_option( 'ai_autopost_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * 設定を保存
     *
     * @param string $key 設定キー
     * @param mixed  $value 値
     */
    public static function update_setting( $key, $value ) {
        $settings = get_option( 'ai_autopost_settings', array() );
        $settings[ $key ] = $value;
        update_option( 'ai_autopost_settings', $settings );
    }

    /**
     * 全設定を取得
     *
     * @return array
     */
    public static function get_all_settings() {
        return get_option( 'ai_autopost_settings', array() );
    }

    /**
     * 全設定を保存
     *
     * @param array $settings 設定配列
     */
    public static function update_all_settings( $settings ) {
        update_option( 'ai_autopost_settings', $settings );
    }
}

/**
 * プラグインを初期化
 */
function ai_autopost_init() {
    return AI_AutoPost::get_instance();
}

// プラグインを開始
add_action( 'plugins_loaded', 'ai_autopost_init' );
