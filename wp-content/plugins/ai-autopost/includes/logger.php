<?php
/**
 * ログ機能
 *
 * @package AI_AutoPost
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ログクラス
 */
class AI_AutoPost_Logger {

    /**
     * ログの最大保存件数
     */
    const MAX_LOGS = 100;

    /**
     * 表示用の最大件数
     */
    const DISPLAY_LIMIT = 30;

    /**
     * ログ種別：成功
     */
    const TYPE_SUCCESS = 'success';

    /**
     * ログ種別：エラー
     */
    const TYPE_ERROR = 'error';

    /**
     * ログ種別：スキップ
     */
    const TYPE_SKIP = 'skip';

    /**
     * ログ種別：情報
     */
    const TYPE_INFO = 'info';

    /**
     * ログを追加
     *
     * @param string $type    ログ種別
     * @param string $message メッセージ
     * @param array  $data    追加データ
     */
    public static function add( $type, $message, $data = array() ) {
        $logs = get_option( 'ai_autopost_logs', array() );

        // 新しいログエントリを作成
        $log_entry = array(
            'id'        => uniqid( 'log_', true ),
            'timestamp' => current_time( 'mysql' ),
            'type'      => sanitize_text_field( $type ),
            'message'   => sanitize_text_field( $message ),
            'data'      => self::sanitize_log_data( $data ),
        );

        // 先頭に追加
        array_unshift( $logs, $log_entry );

        // 最大件数を超えたら古いログを削除
        if ( count( $logs ) > self::MAX_LOGS ) {
            $logs = array_slice( $logs, 0, self::MAX_LOGS );
        }

        update_option( 'ai_autopost_logs', $logs );
    }

    /**
     * ログデータをサニタイズ
     *
     * @param array $data データ
     * @return array
     */
    private static function sanitize_log_data( $data ) {
        if ( ! is_array( $data ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $data as $key => $value ) {
            $key = sanitize_key( $key );
            if ( is_array( $value ) ) {
                $sanitized[ $key ] = self::sanitize_log_data( $value );
            } elseif ( is_string( $value ) ) {
                // APIキーなど機密情報をマスク
                $sanitized[ $key ] = self::mask_sensitive_data( sanitize_text_field( $value ) );
            } else {
                $sanitized[ $key ] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * 機密情報をマスク
     *
     * @param string $value 値
     * @return string
     */
    private static function mask_sensitive_data( $value ) {
        // APIキーパターンをマスク（sk-で始まる文字列）
        $value = preg_replace( '/sk-[a-zA-Z0-9]{20,}/', 'sk-****（マスク済み）', $value );

        // Bearerトークンをマスク
        $value = preg_replace( '/Bearer\s+[a-zA-Z0-9\-_.]+/', 'Bearer ****（マスク済み）', $value );

        return $value;
    }

    /**
     * ログを取得
     *
     * @param int $limit 取得件数
     * @return array
     */
    public static function get( $limit = null ) {
        $logs = get_option( 'ai_autopost_logs', array() );

        if ( null === $limit ) {
            $limit = self::DISPLAY_LIMIT;
        }

        return array_slice( $logs, 0, $limit );
    }

    /**
     * 全ログを取得
     *
     * @return array
     */
    public static function get_all() {
        return get_option( 'ai_autopost_logs', array() );
    }

    /**
     * ログをクリア
     */
    public static function clear() {
        update_option( 'ai_autopost_logs', array() );
    }

    /**
     * 成功ログを追加
     *
     * @param string $message メッセージ
     * @param array  $data    追加データ
     */
    public static function success( $message, $data = array() ) {
        self::add( self::TYPE_SUCCESS, $message, $data );
    }

    /**
     * エラーログを追加
     *
     * @param string $message メッセージ
     * @param array  $data    追加データ
     */
    public static function error( $message, $data = array() ) {
        self::add( self::TYPE_ERROR, $message, $data );
    }

    /**
     * スキップログを追加
     *
     * @param string $message メッセージ
     * @param array  $data    追加データ
     */
    public static function skip( $message, $data = array() ) {
        self::add( self::TYPE_SKIP, $message, $data );
    }

    /**
     * 情報ログを追加
     *
     * @param string $message メッセージ
     * @param array  $data    追加データ
     */
    public static function info( $message, $data = array() ) {
        self::add( self::TYPE_INFO, $message, $data );
    }

    /**
     * ログ種別のラベルを取得
     *
     * @param string $type ログ種別
     * @return string
     */
    public static function get_type_label( $type ) {
        $labels = array(
            self::TYPE_SUCCESS => '成功',
            self::TYPE_ERROR   => 'エラー',
            self::TYPE_SKIP    => 'スキップ',
            self::TYPE_INFO    => '情報',
        );
        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }

    /**
     * ログ種別のCSSクラスを取得
     *
     * @param string $type ログ種別
     * @return string
     */
    public static function get_type_class( $type ) {
        $classes = array(
            self::TYPE_SUCCESS => 'log-success',
            self::TYPE_ERROR   => 'log-error',
            self::TYPE_SKIP    => 'log-skip',
            self::TYPE_INFO    => 'log-info',
        );
        return isset( $classes[ $type ] ) ? $classes[ $type ] : '';
    }
}
