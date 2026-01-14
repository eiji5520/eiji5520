<?php
/**
 * Cron Trigger
 *
 * サーバーcronから呼び出される外部トリガーファイル
 * シンレンタルサーバーなど、アクセスが無いとWP-Cronが動作しない環境で使用
 *
 * 使用方法：
 * curl -A "Mozilla/5.0" -s "https://your-domain.com/wp-content/plugins/ai-autopost/includes/cron-trigger.php?token=YOUR_TOKEN"
 *
 * @package AI_AutoPost
 */

// タイムアウトを延長
set_time_limit( 300 );

// エラー表示を無効化（本番環境向け）
ini_set( 'display_errors', 0 );

// WordPress環境を読み込み
$wp_load_path = dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';

if ( ! file_exists( $wp_load_path ) ) {
    http_response_code( 500 );
    echo 'error: wp-load.php not found';
    exit;
}

require_once $wp_load_path;

// トークンを取得
$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

// 設定からトークンを取得
$settings   = get_option( 'ai_autopost_settings', array() );
$valid_token = isset( $settings['cron_token'] ) ? $settings['cron_token'] : '';

// トークンが空の場合はエラー
if ( empty( $valid_token ) ) {
    http_response_code( 500 );
    echo 'error: cron token not configured';
    exit;
}

// トークン検証（タイミング攻撃対策としてhash_equalsを使用）
if ( ! hash_equals( $valid_token, $token ) ) {
    http_response_code( 403 );
    echo 'error: invalid token';

    // 不正アクセスをログに記録
    if ( class_exists( 'AI_AutoPost_Logger' ) ) {
        AI_AutoPost_Logger::error(
            'cron-trigger: 無効なトークンでのアクセスを検知',
            array(
                'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown',
                'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown',
            )
        );
    }

    exit;
}

// 必要なクラスが読み込まれているか確認
if ( ! function_exists( 'ai_autopost_run_cron_once' ) ) {
    // プラグインファイルを手動で読み込み
    $plugin_file = dirname( __DIR__ ) . '/ai-autopost.php';
    if ( file_exists( $plugin_file ) ) {
        require_once $plugin_file;
    }
}

// 関数が存在するか再確認
if ( ! function_exists( 'ai_autopost_run_cron_once' ) ) {
    http_response_code( 500 );
    echo 'error: plugin not loaded';
    exit;
}

// キューの件数を確認
$queue_count = 0;
if ( class_exists( 'AI_AutoPost_Generator' ) ) {
    $queue_count = AI_AutoPost_Generator::get_queue_count();
}

// キューが空の場合
if ( 0 === $queue_count ) {
    http_response_code( 200 );
    echo 'ok: queue is empty';
    exit;
}

// 記事生成を実行
$result = ai_autopost_run_cron_once();

// 結果を出力
http_response_code( 200 );
header( 'Content-Type: text/plain; charset=utf-8' );

if ( $result['success'] ) {
    $stats = $result['result'];
    printf(
        "ok: success=%d, failed=%d, skipped=%d\n",
        $stats['success'],
        $stats['failed'],
        $stats['skipped']
    );
} else {
    printf( "error: %s\n", $result['message'] );
}

exit;
