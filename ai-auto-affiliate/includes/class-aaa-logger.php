<?php
/**
 * AAA_Logger クラス
 *
 * ログ管理を担当するシングルトンクラス
 * - ログファイルへの書き込み
 * - ログローテーション（直近N行保持）
 * - APIキーやプロンプト全文はログに出力しない
 *
 * @package AI_Auto_Affiliate
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AAA_Logger
 */
class AAA_Logger {

    /**
     * シングルトンインスタンス
     *
     * @var AAA_Logger|null
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @return AAA_Logger
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ（プライベート）
     */
    private function __construct() {
        $this->ensure_log_directory();
    }

    /**
     * ログディレクトリの存在を確認・作成
     */
    private function ensure_log_directory() {
        if ( ! file_exists( AAA_LOG_DIR ) ) {
            wp_mkdir_p( AAA_LOG_DIR );
        }
    }

    /**
     * ログを記録
     *
     * @param string $level   ログレベル (INFO, WARNING, ERROR)
     * @param string $message メッセージ
     * @param array  $context 追加コンテキスト（APIキーは自動除外）
     */
    public function log( $level, $message, $context = array() ) {
        // コンテキストから機密情報を除外
        $safe_context = $this->sanitize_context( $context );

        // ログ行を作成
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $log_line  = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            strtoupper( $level ),
            $message
        );

        if ( ! empty( $safe_context ) ) {
            $log_line .= ' | Context: ' . wp_json_encode( $safe_context, JSON_UNESCAPED_UNICODE );
        }

        $log_line .= PHP_EOL;

        // ファイルに書き込み
        $this->write_to_file( $log_line );

        // ローテーションチェック（10回に1回程度）
        if ( wp_rand( 1, 10 ) === 1 ) {
            $this->rotate_log();
        }
    }

    /**
     * INFOレベルのログ
     *
     * @param string $message メッセージ
     * @param array  $context 追加コンテキスト
     */
    public function info( $message, $context = array() ) {
        $this->log( 'INFO', $message, $context );
    }

    /**
     * WARNINGレベルのログ
     *
     * @param string $message メッセージ
     * @param array  $context 追加コンテキスト
     */
    public function warning( $message, $context = array() ) {
        $this->log( 'WARNING', $message, $context );
    }

    /**
     * ERRORレベルのログ
     *
     * @param string $message メッセージ
     * @param array  $context 追加コンテキスト
     */
    public function error( $message, $context = array() ) {
        $this->log( 'ERROR', $message, $context );
    }

    /**
     * コンテキストから機密情報を除外
     *
     * @param array $context コンテキスト配列
     * @return array サニタイズ済みコンテキスト
     */
    private function sanitize_context( $context ) {
        // 除外するキー
        $sensitive_keys = array(
            'api_key',
            'apikey',
            'api-key',
            'secret',
            'password',
            'token',
            'prompt',           // プロンプト全文は出力しない
            'full_prompt',
            'content',          // 長文コンテンツは出力しない
            'response_body',    // APIレスポンス本文は出力しない
        );

        $safe_context = array();

        foreach ( $context as $key => $value ) {
            $lower_key = strtolower( $key );

            // 機密キーはスキップ
            $is_sensitive = false;
            foreach ( $sensitive_keys as $sensitive ) {
                if ( strpos( $lower_key, $sensitive ) !== false ) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ( $is_sensitive ) {
                $safe_context[ $key ] = '[REDACTED]';
                continue;
            }

            // 値が配列の場合は再帰的にサニタイズ
            if ( is_array( $value ) ) {
                $safe_context[ $key ] = $this->sanitize_context( $value );
            } elseif ( is_string( $value ) && strlen( $value ) > 200 ) {
                // 長い文字列は切り詰め
                $safe_context[ $key ] = substr( $value, 0, 200 ) . '...[truncated]';
            } else {
                $safe_context[ $key ] = $value;
            }
        }

        return $safe_context;
    }

    /**
     * ログファイルに書き込み
     *
     * @param string $log_line ログ行
     */
    private function write_to_file( $log_line ) {
        $this->ensure_log_directory();

        // ファイルに追記
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( AAA_LOG_FILE, $log_line, FILE_APPEND | LOCK_EX );
    }

    /**
     * ログローテーション（直近N行を保持）
     */
    private function rotate_log() {
        if ( ! file_exists( AAA_LOG_FILE ) ) {
            return;
        }

        $max_lines = defined( 'AAA_LOG_MAX_LINES' ) ? AAA_LOG_MAX_LINES : 1000;

        // ファイルサイズが小さければスキップ（パフォーマンス向上）
        $file_size = filesize( AAA_LOG_FILE );
        if ( $file_size < 50000 ) { // 50KB未満ならスキップ
            return;
        }

        // ファイルを読み込み
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( AAA_LOG_FILE );
        if ( false === $content ) {
            return;
        }

        $lines = explode( PHP_EOL, $content );

        // 行数がmax_linesを超えていたらトリミング
        if ( count( $lines ) > $max_lines ) {
            $lines = array_slice( $lines, -$max_lines );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( AAA_LOG_FILE, implode( PHP_EOL, $lines ), LOCK_EX );
        }
    }

    /**
     * ログファイルの内容を取得（管理画面表示用）
     *
     * @param int $lines 取得する行数
     * @return string ログ内容
     */
    public function get_recent_logs( $lines = 100 ) {
        if ( ! file_exists( AAA_LOG_FILE ) ) {
            return 'ログファイルが存在しません。';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( AAA_LOG_FILE );
        if ( false === $content ) {
            return 'ログファイルを読み込めませんでした。';
        }

        $all_lines = explode( PHP_EOL, $content );
        $recent    = array_slice( $all_lines, -$lines );

        return implode( PHP_EOL, $recent );
    }

    /**
     * ログファイルをクリア
     *
     * @return bool 成功したかどうか
     */
    public function clear_logs() {
        if ( file_exists( AAA_LOG_FILE ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            return file_put_contents( AAA_LOG_FILE, '' ) !== false;
        }
        return true;
    }
}
