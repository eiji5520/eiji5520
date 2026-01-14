<?php
/**
 * 設定機能
 *
 * @package AI_AutoPost
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 設定クラス
 */
class AI_AutoPost_Settings {

    /**
     * オプション名
     */
    const OPTION_NAME = 'ai_autopost_settings';

    /**
     * 設定グループ
     */
    const OPTION_GROUP = 'ai_autopost_settings_group';

    /**
     * インスタンス
     *
     * @var AI_AutoPost_Settings
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @return AI_AutoPost_Settings
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
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * 設定を登録
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * 設定をサニタイズ
     *
     * @param array $input 入力値
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $current   = get_option( self::OPTION_NAME, array() );

        // APIキー（空の場合は現在の値を維持）
        if ( ! empty( $input['api_key'] ) ) {
            $sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
        } else {
            $sanitized['api_key'] = isset( $current['api_key'] ) ? $current['api_key'] : '';
        }

        // モデル名
        $allowed_models           = array( 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' );
        $sanitized['model']       = isset( $input['model'] ) && in_array( $input['model'], $allowed_models, true )
            ? $input['model']
            : 'gpt-4.1-mini';

        // temperature（0.0〜2.0）
        $sanitized['temperature'] = isset( $input['temperature'] )
            ? max( 0.0, min( 2.0, floatval( $input['temperature'] ) ) )
            : 0.7;

        // 投稿ステータス
        $sanitized['post_status'] = isset( $input['post_status'] ) && in_array( $input['post_status'], array( 'draft', 'publish' ), true )
            ? $input['post_status']
            : 'draft';

        // カテゴリID
        $sanitized['category_id'] = isset( $input['category_id'] )
            ? absint( $input['category_id'] )
            : 0;

        // 想定デバイス
        $sanitized['device'] = isset( $input['device'] )
            ? sanitize_text_field( $input['device'] )
            : 'Android/iPhone';

        // 想定状況
        $sanitized['situation'] = isset( $input['situation'] )
            ? sanitize_textarea_field( $input['situation'] )
            : 'アプリや端末で問題が起きて困っている';

        // 自動実行ON/OFF
        $sanitized['auto_enabled'] = ! empty( $input['auto_enabled'] );

        // 1回の実行で生成する本数（1〜10）
        $sanitized['posts_per_run'] = isset( $input['posts_per_run'] )
            ? max( 1, min( 10, absint( $input['posts_per_run'] ) ) )
            : 3;

        // 実行時間（HH:MM形式）
        if ( isset( $input['run_time'] ) && preg_match( '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $input['run_time'] ) ) {
            $sanitized['run_time'] = $input['run_time'];
        } else {
            $sanitized['run_time'] = '09:10';
        }

        // cronトークン（変更がある場合のみ更新、または再生成）
        if ( ! empty( $input['regenerate_token'] ) ) {
            $sanitized['cron_token'] = wp_generate_password( 32, false );
        } elseif ( isset( $current['cron_token'] ) && ! empty( $current['cron_token'] ) ) {
            $sanitized['cron_token'] = $current['cron_token'];
        } else {
            $sanitized['cron_token'] = wp_generate_password( 32, false );
        }

        // 設定変更時にCronを再スケジュール
        if ( class_exists( 'AI_AutoPost_Cron' ) ) {
            // 設定が保存された後にCronを更新する
            add_action( 'shutdown', array( 'AI_AutoPost_Cron', 'reschedule_event' ) );
        }

        return $sanitized;
    }

    /**
     * デフォルト設定を取得
     *
     * @return array
     */
    public static function get_defaults() {
        return array(
            'api_key'        => '',
            'model'          => 'gpt-4.1-mini',
            'temperature'    => 0.7,
            'post_status'    => 'draft',
            'category_id'    => 0,
            'device'         => 'Android/iPhone',
            'situation'      => 'アプリや端末で問題が起きて困っている',
            'auto_enabled'   => false,
            'posts_per_run'  => 3,
            'run_time'       => '09:10',
            'cron_token'     => '',
        );
    }

    /**
     * 設定を取得
     *
     * @param string $key     設定キー
     * @param mixed  $default デフォルト値
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = get_option( self::OPTION_NAME, array() );
        $defaults = self::get_defaults();

        if ( null === $default && isset( $defaults[ $key ] ) ) {
            $default = $defaults[ $key ];
        }

        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * 全設定を取得
     *
     * @return array
     */
    public static function get_all() {
        $settings = get_option( self::OPTION_NAME, array() );
        return wp_parse_args( $settings, self::get_defaults() );
    }

    /**
     * 設定を更新
     *
     * @param string $key   設定キー
     * @param mixed  $value 値
     */
    public static function update( $key, $value ) {
        $settings         = get_option( self::OPTION_NAME, array() );
        $settings[ $key ] = $value;
        update_option( self::OPTION_NAME, $settings );
    }

    /**
     * APIキーが設定されているか確認
     *
     * @return bool
     */
    public static function has_api_key() {
        $api_key = self::get( 'api_key' );
        return ! empty( $api_key );
    }

    /**
     * cron-trigger URLを取得
     *
     * @return string
     */
    public static function get_cron_trigger_url() {
        $token = self::get( 'cron_token' );
        if ( empty( $token ) ) {
            $token = wp_generate_password( 32, false );
            self::update( 'cron_token', $token );
        }

        return plugins_url( 'includes/cron-trigger.php', AI_AUTOPOST_PLUGIN_DIR . 'ai-autopost.php' )
            . '?token=' . urlencode( $token );
    }

    /**
     * APIキーをマスク表示用に変換
     *
     * @param string $api_key APIキー
     * @return string
     */
    public static function mask_api_key( $api_key ) {
        if ( empty( $api_key ) ) {
            return '';
        }
        $length = strlen( $api_key );
        if ( $length <= 8 ) {
            return str_repeat( '*', $length );
        }
        return substr( $api_key, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $api_key, -4 );
    }
}

// 初期化
AI_AutoPost_Settings::get_instance();
