<?php
/**
 * Settings Class
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_Settings {

    use Blog_Agent_Encryption;

    /**
     * Option group
     */
    private const OPTION_GROUP = 'blog_agent_settings';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_blog_agent_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_blog_agent_emergency_stop', [ $this, 'ajax_emergency_stop' ] );
    }

    /**
     * Add admin menu
     */
    public function add_menu(): void {
        add_menu_page(
            __( 'Blog Agent', 'blog-agent' ),
            __( 'Blog Agent', 'blog-agent' ),
            'manage_options',
            'blog-agent',
            [ $this, 'render_dashboard' ],
            'dashicons-edit-page',
            30
        );

        add_submenu_page(
            'blog-agent',
            __( 'Dashboard', 'blog-agent' ),
            __( 'Dashboard', 'blog-agent' ),
            'manage_options',
            'blog-agent',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'blog-agent',
            __( 'Settings', 'blog-agent' ),
            __( 'Settings', 'blog-agent' ),
            'manage_options',
            'blog-agent-settings',
            [ $this, 'render_settings' ]
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'blog-agent' ) ) {
            return;
        }

        wp_enqueue_style(
            'blog-agent-admin',
            BLOG_AGENT_URL . 'admin/css/admin.css',
            [],
            BLOG_AGENT_VERSION
        );

        wp_enqueue_script(
            'blog-agent-admin',
            BLOG_AGENT_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            BLOG_AGENT_VERSION,
            true
        );

        wp_localize_script( 'blog-agent-admin', 'blogAgentAdmin', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'blog_agent_nonce' ),
            'strings'   => [
                'testing'      => __( 'Testing connection...', 'blog-agent' ),
                'stopping'     => __( 'Stopping...', 'blog-agent' ),
                'confirmStop'  => __( 'Are you sure you want to emergency stop all auto-posting?', 'blog-agent' ),
                'generating'   => __( 'Generating...', 'blog-agent' ),
                'success'      => __( 'Success!', 'blog-agent' ),
                'error'        => __( 'Error', 'blog-agent' ),
            ],
        ] );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        // AI Provider Settings
        register_setting( self::OPTION_GROUP, 'blog_agent_provider', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'openai',
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_openai_api_key', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_api_key' ],
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_gemini_api_key', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_api_key' ],
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_model', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'gpt-4o-mini',
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_temperature', [
            'type'              => 'number',
            'sanitize_callback' => [ $this, 'sanitize_temperature' ],
            'default'           => 0.7,
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_max_tokens', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 4000,
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_timeout', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 60,
        ] );

        // Auto-post Settings
        register_setting( self::OPTION_GROUP, 'blog_agent_autopost_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_autopost_mode', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'draft',
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_autopost_condition', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'qa_approved',
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_schedule_delay', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 60,
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_daily_limit', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ] );

        register_setting( self::OPTION_GROUP, 'blog_agent_post_interval', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 60,
        ] );

        // QA Settings
        register_setting( self::OPTION_GROUP, 'blog_agent_forbidden_words', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => "必ず\n確実に\n絶対に\n100%",
        ] );

        // Add settings sections
        add_settings_section(
            'blog_agent_ai_section',
            __( 'AI Provider Settings', 'blog-agent' ),
            [ $this, 'render_ai_section' ],
            'blog-agent-settings'
        );

        add_settings_section(
            'blog_agent_autopost_section',
            __( 'Auto-Post Settings', 'blog-agent' ),
            [ $this, 'render_autopost_section' ],
            'blog-agent-settings'
        );

        add_settings_section(
            'blog_agent_qa_section',
            __( 'QA Settings', 'blog-agent' ),
            [ $this, 'render_qa_section' ],
            'blog-agent-settings'
        );
    }

    /**
     * Sanitize API key (encrypt it)
     */
    public function sanitize_api_key( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }

        // If the value contains asterisks, it's the masked value - keep the old one
        if ( str_contains( $value, '*' ) ) {
            // Get the field name from the current filter
            $field = str_replace( 'sanitize_option_', '', current_filter() );
            return get_option( $field, '' );
        }

        return $this->encrypt( $value );
    }

    /**
     * Sanitize temperature
     */
    public function sanitize_temperature( $value ): float {
        $value = (float) $value;
        return max( 0, min( 2, $value ) );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $autopost_enabled = get_option( 'blog_agent_autopost_enabled', false );
        $today_count      = (int) get_option( 'blog_agent_today_post_count', 0 );
        $daily_limit      = (int) get_option( 'blog_agent_daily_limit', 10 );
        $last_error       = get_option( 'blog_agent_last_error', [] );

        // Get queue stats
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';
        $queue_stats = $wpdb->get_row(
            "SELECT
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
            FROM {$table_name}"
        );

        require BLOG_AGENT_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render settings page
     */
    public function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require BLOG_AGENT_PATH . 'admin/views/settings.php';
    }

    /**
     * Render AI section description
     */
    public function render_ai_section(): void {
        echo '<p>' . esc_html__( 'Configure your AI provider settings below.', 'blog-agent' ) . '</p>';
    }

    /**
     * Render autopost section description
     */
    public function render_autopost_section(): void {
        echo '<p>' . esc_html__( 'Configure automatic posting behavior. Auto-post is disabled by default for safety.', 'blog-agent' ) . '</p>';
    }

    /**
     * Render QA section description
     */
    public function render_qa_section(): void {
        echo '<p>' . esc_html__( 'Configure quality assurance settings.', 'blog-agent' ) . '</p>';
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection(): void {
        check_ajax_referer( 'blog_agent_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'blog-agent' ) );
        }

        $provider = sanitize_text_field( $_POST['provider'] ?? 'openai' );
        $client   = Blog_Agent_API_Factory::get_client( $provider );
        $result   = $client->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * AJAX: Emergency stop
     */
    public function ajax_emergency_stop(): void {
        check_ajax_referer( 'blog_agent_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'blog-agent' ) );
        }

        // Disable autopost
        update_option( 'blog_agent_autopost_enabled', false );

        // Cancel pending jobs
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';
        $wpdb->update(
            $table_name,
            [ 'status' => 'cancelled' ],
            [ 'status' => 'pending', 'job_type' => 'autopost' ]
        );

        wp_send_json_success( __( 'Auto-posting has been stopped and all pending jobs have been cancelled.', 'blog-agent' ) );
    }

    /**
     * Get decrypted API key for display (masked)
     */
    public function get_masked_api_key( string $option_name ): string {
        $encrypted = get_option( $option_name, '' );
        if ( empty( $encrypted ) ) {
            return '';
        }

        $decrypted = $this->decrypt( $encrypted );
        return $this->mask_api_key( $decrypted );
    }
}
