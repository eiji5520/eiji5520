<?php
/**
 * Plugin Name: AI Auto Affiliate
 * Plugin URI: https://example.com/ai-auto-affiliate
 * Description: AIで記事を生成し、WordPressへ下書き保存。A8広告ショートコードを自然に挿入できるプラグイン。
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-auto-affiliate
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * プラグイン定数の定義
 */
define( 'AAA_VERSION', '1.0.0' );
define( 'AAA_PLUGIN_FILE', __FILE__ );
define( 'AAA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AAA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AAA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// オプション名（設定は1つの配列にまとめる）
define( 'AAA_OPTION_NAME', 'aaa_settings' );

// ログディレクトリ
define( 'AAA_LOG_DIR', WP_CONTENT_DIR . '/uploads/ai-auto-affiliate/' );
define( 'AAA_LOG_FILE', AAA_LOG_DIR . 'log.txt' );
define( 'AAA_LOG_MAX_LINES', 1000 );

// Capability（管理者のみ）
define( 'AAA_CAPABILITY', 'manage_options' );

/**
 * プラグイン有効化時の処理
 */
function aaa_activate() {
    // ログディレクトリの作成
    if ( ! file_exists( AAA_LOG_DIR ) ) {
        wp_mkdir_p( AAA_LOG_DIR );
    }

    // .htaccess でログファイルへの直接アクセスを防止
    $htaccess_file = AAA_LOG_DIR . '.htaccess';
    if ( ! file_exists( $htaccess_file ) ) {
        $htaccess_content = "Order deny,allow\nDeny from all";
        file_put_contents( $htaccess_file, $htaccess_content );
    }

    // index.php でディレクトリリスティング防止
    $index_file = AAA_LOG_DIR . 'index.php';
    if ( ! file_exists( $index_file ) ) {
        file_put_contents( $index_file, '<?php // Silence is golden.' );
    }

    // デフォルト設定の初期化（既存設定がない場合のみ）
    if ( false === get_option( AAA_OPTION_NAME ) ) {
        $default_settings = aaa_get_default_settings();
        add_option( AAA_OPTION_NAME, $default_settings );
    }

    // リライトルールをフラッシュ
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'aaa_activate' );

/**
 * プラグイン無効化時の処理
 */
function aaa_deactivate() {
    // リライトルールをフラッシュ
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'aaa_deactivate' );

/**
 * デフォルト設定を取得
 *
 * @return array デフォルト設定値
 */
function aaa_get_default_settings() {
    return array(
        'provider'      => 'claude',
        'api_key'       => '',
        'model'         => 'claude-sonnet-4-20250514',
        'template'      => aaa_get_default_template(),
        'min_chars'     => 2000,
        'max_chars'     => 4000,
        'ng_words'      => "絶対に\n確実に\n100%\n必ず\n間違いなく",
        'show_pr_label' => true,
        'a8_slots'      => array(),
    );
}

/**
 * デフォルトの記事構成テンプレートを取得
 *
 * @return string デフォルトテンプレート
 */
function aaa_get_default_template() {
    return <<<'TEMPLATE'
以下の構成で記事を作成してください：

## 結論（最初に結論を述べる）
読者が抱える問題に対する答えを最初に提示します。

## 原因・背景
なぜその問題が起きるのか、背景を説明します。

## すぐできる対処法
具体的で実践可能な解決策を3〜5つ提示します。

## 注意点・よくある失敗
対処する際の注意点や、やってはいけないことを説明します。

## おすすめの解決策
{{AD_INSERTION_POINT}}
ここに広告が挿入されます。自然な流れで商品・サービスを紹介します。

## まとめ
記事の要点を簡潔にまとめます。
TEMPLATE;
}

/**
 * 設定値を取得（マージ済み）
 *
 * @param string|null $key 特定のキーのみ取得する場合
 * @return mixed 設定値
 */
function aaa_get_settings( $key = null ) {
    $settings = get_option( AAA_OPTION_NAME, array() );
    $defaults = aaa_get_default_settings();

    // デフォルト値とマージ
    $settings = wp_parse_args( $settings, $defaults );

    if ( null !== $key ) {
        return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
    }

    return $settings;
}

/**
 * 設定値を更新
 *
 * @param array $new_settings 新しい設定値
 * @return bool 更新成功したかどうか
 */
function aaa_update_settings( $new_settings ) {
    $current = aaa_get_settings();
    $merged  = wp_parse_args( $new_settings, $current );

    return update_option( AAA_OPTION_NAME, $merged );
}

/**
 * APIキーを伏字にする（表示用）
 *
 * @param string $api_key APIキー
 * @return string 伏字化されたAPIキー
 */
function aaa_mask_api_key( $api_key ) {
    if ( empty( $api_key ) ) {
        return '';
    }

    $length = strlen( $api_key );

    if ( $length <= 8 ) {
        return str_repeat( '•', $length );
    }

    // 最後の4文字だけ表示
    return str_repeat( '•', $length - 4 ) . substr( $api_key, -4 );
}

/**
 * クラスファイルの読み込み
 */
function aaa_load_classes() {
    $classes = array(
        'class-aaa-logger.php',
        'class-aaa-settings.php',
        'class-aaa-api-client.php',
        'class-aaa-post-creator.php',
        'class-aaa-generator.php',
    );

    foreach ( $classes as $class_file ) {
        $file_path = AAA_PLUGIN_DIR . 'includes/' . $class_file;
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}

/**
 * プラグインの初期化
 */
function aaa_init() {
    // クラスファイルを読み込み
    aaa_load_classes();

    // 管理画面でのみ初期化
    if ( is_admin() ) {
        // ロガーの初期化
        if ( class_exists( 'AAA_Logger' ) ) {
            AAA_Logger::get_instance();
        }

        // 設定ページの初期化
        if ( class_exists( 'AAA_Settings' ) ) {
            new AAA_Settings();
        }

        // 生成ページの初期化
        if ( class_exists( 'AAA_Generator' ) ) {
            new AAA_Generator();
        }
    }
}
add_action( 'plugins_loaded', 'aaa_init' );

/**
 * 管理画面用のスタイルとスクリプトを読み込み
 *
 * @param string $hook 現在のページフック
 */
function aaa_admin_enqueue_scripts( $hook ) {
    // プラグインのページでのみ読み込み
    if ( strpos( $hook, 'ai-auto-affiliate' ) === false ) {
        return;
    }

    // CSS
    $css_file = AAA_PLUGIN_DIR . 'admin/css/admin-style.css';
    if ( file_exists( $css_file ) ) {
        wp_enqueue_style(
            'aaa-admin-style',
            AAA_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            AAA_VERSION
        );
    }

    // JavaScript
    $js_file = AAA_PLUGIN_DIR . 'admin/js/admin-script.js';
    if ( file_exists( $js_file ) ) {
        wp_enqueue_script(
            'aaa-admin-script',
            AAA_PLUGIN_URL . 'admin/js/admin-script.js',
            array( 'jquery' ),
            AAA_VERSION,
            true
        );

        // JSに渡すデータ
        wp_localize_script(
            'aaa-admin-script',
            'aaaAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'aaa_generate_nonce' ),
                'i18n'    => array(
                    'generating'    => '記事を生成中です...',
                    'success'       => '記事の下書きを作成しました。',
                    'error'         => 'エラーが発生しました。',
                    'confirmDelete' => 'このショートコードを削除しますか？',
                ),
            )
        );
    }
}
add_action( 'admin_enqueue_scripts', 'aaa_admin_enqueue_scripts' );

/**
 * プラグインアンインストール時の処理
 * （uninstall.php として別ファイルにするのがベストプラクティスだが、
 *   ここでは register_uninstall_hook を使用）
 */
function aaa_uninstall() {
    // オプションを削除
    delete_option( AAA_OPTION_NAME );

    // ログディレクトリを削除（任意）
    if ( file_exists( AAA_LOG_DIR ) ) {
        $files = glob( AAA_LOG_DIR . '*' );
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                unlink( $file );
            }
        }
        rmdir( AAA_LOG_DIR );
    }
}
// register_uninstall_hook( __FILE__, 'aaa_uninstall' );
// ↑ アンインストール時にデータを消す場合はコメントを外す

/**
 * プラグイン一覧ページに設定リンクを追加
 *
 * @param array $links 既存のリンク
 * @return array 修正後のリンク
 */
function aaa_plugin_action_links( $links ) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'admin.php?page=ai-auto-affiliate-settings' ) ),
        esc_html__( '設定', 'ai-auto-affiliate' )
    );

    array_unshift( $links, $settings_link );

    return $links;
}
add_filter( 'plugin_action_links_' . AAA_PLUGIN_BASENAME, 'aaa_plugin_action_links' );
