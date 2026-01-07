<?php
/**
 * Auto-Post Class with Safety Controls
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_Autopost {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'blog_agent_article_approved', [ $this, 'handle_approval' ] );
        add_action( 'blog_agent_daily_reset', [ $this, 'daily_reset' ] );
    }

    /**
     * Handle article approval - trigger autopost if conditions met
     */
    public function handle_approval( int $post_id ): void {
        if ( ! $this->is_autopost_enabled() ) {
            return;
        }

        if ( $this->can_autopost( $post_id ) ) {
            $this->process_autopost_job( $post_id );
        }
    }

    /**
     * Check if autopost is enabled
     */
    public function is_autopost_enabled(): bool {
        return (bool) get_option( 'blog_agent_autopost_enabled', false );
    }

    /**
     * Check if article can be auto-posted
     */
    public function can_autopost( int $post_id ): bool {
        // Check if autopost is enabled
        if ( ! $this->is_autopost_enabled() ) {
            return false;
        }

        // Check daily limit
        if ( ! $this->check_daily_limit() ) {
            return false;
        }

        // Check post interval
        if ( ! $this->check_post_interval() ) {
            return false;
        }

        // Check QA status
        if ( ! blog_agent()->qa->passes_qa_for_autopost( $post_id ) ) {
            return false;
        }

        // Check approval requirement
        $condition = get_option( 'blog_agent_autopost_condition', 'qa_approved' );
        if ( $condition === 'qa_approved' ) {
            $approval_status = get_post_meta( $post_id, '_blog_agent_approval_status', true );
            if ( $approval_status !== 'approved' ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check daily post limit
     */
    private function check_daily_limit(): bool {
        $today_count = $this->get_today_post_count();
        $daily_limit = (int) get_option( 'blog_agent_daily_limit', 10 );

        return $today_count < $daily_limit;
    }

    /**
     * Check post interval
     */
    private function check_post_interval(): bool {
        $last_post_time = (int) get_option( 'blog_agent_last_autopost_time', 0 );
        $interval       = (int) get_option( 'blog_agent_post_interval', 60 ) * 60; // Convert to seconds

        return ( time() - $last_post_time ) >= $interval;
    }

    /**
     * Get today's auto-post count
     */
    private function get_today_post_count(): int {
        $last_date   = get_option( 'blog_agent_last_post_date', '' );
        $today       = current_time( 'Y-m-d' );

        if ( $last_date !== $today ) {
            // Reset count for new day
            update_option( 'blog_agent_today_post_count', 0 );
            update_option( 'blog_agent_last_post_date', $today );
            return 0;
        }

        return (int) get_option( 'blog_agent_today_post_count', 0 );
    }

    /**
     * Process autopost job
     */
    public function process_autopost_job( int $post_id ): array {
        // Double-check autopost is still enabled (emergency stop check)
        if ( ! $this->is_autopost_enabled() ) {
            return [
                'success' => false,
                'error'   => __( 'Auto-posting has been disabled.', 'blog-agent' ),
            ];
        }

        // Verify the post exists and is a draft
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status === 'publish' ) {
            return [
                'success' => false,
                'error'   => __( 'Post not found or already published.', 'blog-agent' ),
            ];
        }

        // Check all conditions again
        if ( ! $this->can_autopost( $post_id ) ) {
            return [
                'success' => false,
                'error'   => __( 'Post does not meet auto-post conditions.', 'blog-agent' ),
            ];
        }

        $mode = get_option( 'blog_agent_autopost_mode', 'draft' );

        switch ( $mode ) {
            case 'publish':
                return $this->publish_immediately( $post_id );

            case 'schedule':
                return $this->schedule_post( $post_id );

            case 'draft':
            default:
                // Do nothing, keep as draft
                return [
                    'success' => true,
                    'message' => __( 'Post kept as draft (auto-post mode is "Draft Only").', 'blog-agent' ),
                ];
        }
    }

    /**
     * Publish post immediately
     */
    private function publish_immediately( int $post_id ): array {
        $result = wp_update_post( [
            'ID'          => $post_id,
            'post_status' => 'publish',
        ], true );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'error'   => $result->get_error_message(),
            ];
        }

        // Update counters
        $this->increment_post_count();

        // Save autopost metadata
        update_post_meta( $post_id, '_blog_agent_autopost_status', 'published' );
        update_post_meta( $post_id, '_blog_agent_autopost_time', current_time( 'mysql' ) );

        return [
            'success' => true,
            'message' => __( 'Post published successfully.', 'blog-agent' ),
            'post_id' => $post_id,
        ];
    }

    /**
     * Schedule post for future publication
     */
    private function schedule_post( int $post_id ): array {
        $schedule_time = $this->calculate_schedule_time();

        $result = wp_update_post( [
            'ID'            => $post_id,
            'post_status'   => 'future',
            'post_date'     => $schedule_time,
            'post_date_gmt' => get_gmt_from_date( $schedule_time ),
        ], true );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'error'   => $result->get_error_message(),
            ];
        }

        // Update counters
        $this->increment_post_count();

        // Save autopost metadata
        update_post_meta( $post_id, '_blog_agent_autopost_status', 'scheduled' );
        update_post_meta( $post_id, '_blog_agent_autopost_scheduled_time', $schedule_time );

        return [
            'success' => true,
            'message' => sprintf( __( 'Post scheduled for %s.', 'blog-agent' ), $schedule_time ),
            'post_id' => $post_id,
        ];
    }

    /**
     * Calculate next available schedule time
     */
    private function calculate_schedule_time(): string {
        $delay_minutes     = (int) get_option( 'blog_agent_schedule_delay', 60 );
        $interval_minutes  = (int) get_option( 'blog_agent_post_interval', 60 );
        $last_schedule     = get_option( 'blog_agent_last_schedule_time', '' );

        // Base time: now + delay
        $base_time = time() + ( $delay_minutes * 60 );

        // If there's a last scheduled time, use that + interval as minimum
        if ( $last_schedule ) {
            $last_timestamp = strtotime( $last_schedule );
            $min_time       = $last_timestamp + ( $interval_minutes * 60 );
            $base_time      = max( $base_time, $min_time );
        }

        $schedule_time = gmdate( 'Y-m-d H:i:s', $base_time );

        // Save for next calculation
        update_option( 'blog_agent_last_schedule_time', $schedule_time );

        return $schedule_time;
    }

    /**
     * Increment daily post count
     */
    private function increment_post_count(): void {
        $today = current_time( 'Y-m-d' );
        $last_date = get_option( 'blog_agent_last_post_date', '' );

        if ( $last_date !== $today ) {
            update_option( 'blog_agent_today_post_count', 1 );
            update_option( 'blog_agent_last_post_date', $today );
        } else {
            $count = (int) get_option( 'blog_agent_today_post_count', 0 );
            update_option( 'blog_agent_today_post_count', $count + 1 );
        }

        update_option( 'blog_agent_last_autopost_time', time() );
    }

    /**
     * Daily reset (cron job)
     */
    public function daily_reset(): void {
        update_option( 'blog_agent_today_post_count', 0 );
        update_option( 'blog_agent_last_post_date', current_time( 'Y-m-d' ) );
        update_option( 'blog_agent_retry_count', 0 );
    }

    /**
     * Emergency stop - disable autopost and cancel pending jobs
     */
    public function emergency_stop(): array {
        // Disable autopost
        update_option( 'blog_agent_autopost_enabled', false );

        // Cancel pending autopost jobs
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';

        $cancelled = $wpdb->update(
            $table_name,
            [ 'status' => 'cancelled' ],
            [
                'status'   => 'pending',
                'job_type' => 'autopost',
            ]
        );

        return [
            'success'   => true,
            'cancelled' => $cancelled ?: 0,
            'message'   => __( 'Auto-posting has been stopped and pending jobs have been cancelled.', 'blog-agent' ),
        ];
    }

    /**
     * Get autopost statistics
     */
    public function get_stats(): array {
        $today_count  = $this->get_today_post_count();
        $daily_limit  = (int) get_option( 'blog_agent_daily_limit', 10 );
        $is_enabled   = $this->is_autopost_enabled();
        $mode         = get_option( 'blog_agent_autopost_mode', 'draft' );
        $condition    = get_option( 'blog_agent_autopost_condition', 'qa_approved' );
        $retry_count  = (int) get_option( 'blog_agent_retry_count', 0 );

        // Get pending queue count
        global $wpdb;
        $table_name    = $wpdb->prefix . 'blog_agent_queue';
        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE job_type = 'autopost' AND status = 'pending'"
        );

        return [
            'enabled'       => $is_enabled,
            'mode'          => $mode,
            'condition'     => $condition,
            'today_count'   => $today_count,
            'daily_limit'   => $daily_limit,
            'remaining'     => max( 0, $daily_limit - $today_count ),
            'pending_jobs'  => (int) $pending_count,
            'retry_count'   => $retry_count,
            'can_post_now'  => $is_enabled && $this->check_daily_limit() && $this->check_post_interval(),
        ];
    }

    /**
     * Get next available slot time
     */
    public function get_next_available_slot(): ?string {
        if ( ! $this->check_daily_limit() ) {
            return null; // No slots available today
        }

        $last_post_time = (int) get_option( 'blog_agent_last_autopost_time', 0 );
        $interval       = (int) get_option( 'blog_agent_post_interval', 60 ) * 60;

        $next_time = max( time(), $last_post_time + $interval );

        return gmdate( 'Y-m-d H:i:s', $next_time );
    }
}
