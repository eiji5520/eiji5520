<?php
/**
 * Cron機能
 *
 * @package AI_AutoPost
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cronクラス
 */
class AI_AutoPost_Cron {

    /**
     * Cronフック名
     */
    const CRON_HOOK = 'ai_autopost_cron_event';

    /**
     * インスタンス
     *
     * @var AI_AutoPost_Cron
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @return AI_AutoPost_Cron
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
        // Cronイベントを登録
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_generation' ) );

        // カスタムスケジュールを追加
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
    }

    /**
     * カスタムCronスケジュールを追加
     *
     * @param array $schedules スケジュール配列
     * @return array
     */
    public function add_cron_schedules( $schedules ) {
        // 5分間隔
        $schedules['ai_autopost_5min'] = array(
            'interval' => 300,
            'display'  => __( '5分ごと', 'ai-autopost' ),
        );

        // 10分間隔
        $schedules['ai_autopost_10min'] = array(
            'interval' => 600,
            'display'  => __( '10分ごと', 'ai-autopost' ),
        );

        return $schedules;
    }

    /**
     * Cronイベントをスケジュール
     */
    public static function schedule_event() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            $settings = AI_AutoPost_Settings::get_all();
            $run_time = isset( $settings['run_time'] ) ? $settings['run_time'] : '09:10';

            // 実行時刻を計算
            $timestamp = self::get_next_run_timestamp( $run_time );

            wp_schedule_event( $timestamp, 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Cronイベントを再スケジュール
     */
    public static function reschedule_event() {
        self::clear_scheduled_event();
        self::schedule_event();
    }

    /**
     * スケジュールされたCronイベントをクリア
     */
    public static function clear_scheduled_event() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }

        // 全てのスケジュールをクリア
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * 次回実行時刻のタイムスタンプを取得
     *
     * @param string $run_time 実行時刻（HH:MM形式）
     * @return int
     */
    private static function get_next_run_timestamp( $run_time ) {
        $parts = explode( ':', $run_time );
        $hour  = isset( $parts[0] ) ? intval( $parts[0] ) : 9;
        $min   = isset( $parts[1] ) ? intval( $parts[1] ) : 10;

        // WordPressのタイムゾーン設定を考慮
        $timezone = wp_timezone();
        $now      = new DateTime( 'now', $timezone );

        // 今日の指定時刻
        $target = new DateTime( 'now', $timezone );
        $target->setTime( $hour, $min, 0 );

        // 過去の時刻なら翌日に設定
        if ( $target <= $now ) {
            $target->modify( '+1 day' );
        }

        return $target->getTimestamp();
    }

    /**
     * スケジュールされた記事生成を実行
     */
    public function run_scheduled_generation() {
        $settings = AI_AutoPost_Settings::get_all();

        // 自動実行が無効の場合は何もしない
        if ( empty( $settings['auto_enabled'] ) ) {
            AI_AutoPost_Logger::info( 'スケジュール実行：自動実行が無効のためスキップしました' );
            return;
        }

        // APIキーチェック
        if ( empty( $settings['api_key'] ) ) {
            AI_AutoPost_Logger::error( 'スケジュール実行：APIキーが設定されていません' );
            return;
        }

        // 実行
        $posts_per_run = isset( $settings['posts_per_run'] ) ? intval( $settings['posts_per_run'] ) : 3;
        $post_status   = isset( $settings['post_status'] ) ? $settings['post_status'] : 'draft';

        AI_AutoPost_Logger::info(
            sprintf( 'スケジュール実行開始：%d件を処理予定', $posts_per_run ),
            array( 'posts_per_run' => $posts_per_run )
        );

        $result = self::run_generation( $posts_per_run, $post_status );

        AI_AutoPost_Logger::info(
            sprintf(
                'スケジュール実行完了：成功%d件、失敗%d件、スキップ%d件',
                $result['success'],
                $result['failed'],
                $result['skipped']
            ),
            $result
        );
    }

    /**
     * 記事生成を実行（手動/cron-trigger用）
     *
     * @param int    $count       生成件数
     * @param string $post_status 投稿ステータス
     * @return array 結果
     */
    public static function run_generation( $count = 1, $post_status = 'draft' ) {
        $result = array(
            'success' => 0,
            'failed'  => 0,
            'skipped' => 0,
            'details' => array(),
        );

        for ( $i = 0; $i < $count; $i++ ) {
            // キューから取得
            $keyword = AI_AutoPost_Generator::get_from_queue();

            if ( null === $keyword ) {
                AI_AutoPost_Logger::info( 'キューが空のため処理を終了します' );
                break;
            }

            // 生成実行
            $gen_result = AI_AutoPost_Generator::generate_and_post( $keyword, $post_status );

            if ( $gen_result['success'] ) {
                // 成功：キューから削除
                AI_AutoPost_Generator::remove_from_queue();
                $result['success']++;
            } elseif ( ! empty( $gen_result['skipped'] ) ) {
                // スキップ：キューから削除
                AI_AutoPost_Generator::remove_from_queue();
                $result['skipped']++;
            } else {
                // 失敗：末尾に移動（リトライ）
                AI_AutoPost_Generator::move_to_queue_end( $keyword );
                $result['failed']++;

                // レート制限の場合は少し待機して次へ
                if ( strpos( $gen_result['message'], '429' ) !== false ) {
                    usleep( 1000000 ); // 1秒待機
                }
            }

            $result['details'][] = array(
                'keyword' => $keyword,
                'result'  => $gen_result,
            );

            // 連続実行時の待機（レート制限対策）
            if ( $i < $count - 1 ) {
                usleep( 500000 ); // 0.5秒待機
            }
        }

        return $result;
    }

    /**
     * 次回実行予定時刻を取得
     *
     * @return string|null
     */
    public static function get_next_scheduled() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        if ( ! $timestamp ) {
            return null;
        }

        // ローカルタイムに変換
        return wp_date( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Cronが正常にスケジュールされているか確認
     *
     * @return bool
     */
    public static function is_scheduled() {
        return (bool) wp_next_scheduled( self::CRON_HOOK );
    }
}

/**
 * cron-trigger.php から呼び出される関数
 * キューから記事を生成して投稿する
 *
 * @return array 実行結果
 */
function ai_autopost_run_cron_once() {
    $settings = AI_AutoPost_Settings::get_all();

    // 自動実行が有効かチェック（triggerの場合は強制実行も可能にする）
    $posts_per_run = isset( $settings['posts_per_run'] ) ? intval( $settings['posts_per_run'] ) : 3;
    $post_status   = isset( $settings['post_status'] ) ? $settings['post_status'] : 'draft';

    // APIキーチェック
    if ( empty( $settings['api_key'] ) ) {
        AI_AutoPost_Logger::error( 'cron-trigger実行：APIキーが設定されていません' );
        return array(
            'success' => false,
            'message' => 'APIキーが設定されていません',
        );
    }

    AI_AutoPost_Logger::info(
        sprintf( 'cron-trigger実行開始：%d件を処理予定', $posts_per_run ),
        array( 'posts_per_run' => $posts_per_run )
    );

    $result = AI_AutoPost_Cron::run_generation( $posts_per_run, $post_status );

    AI_AutoPost_Logger::info(
        sprintf(
            'cron-trigger実行完了：成功%d件、失敗%d件、スキップ%d件',
            $result['success'],
            $result['failed'],
            $result['skipped']
        ),
        $result
    );

    return array(
        'success'  => true,
        'message'  => 'cron-trigger実行完了',
        'result'   => $result,
    );
}
