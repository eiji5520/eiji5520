<?php
/**
 * Plugin Name: Blog Agent
 * Plugin URI: https://example.com/blog-agent
 * Description: AI-powered blog article generator with auto-posting capabilities. Supports OpenAI ChatGPT and Google Gemini.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Blog Agent Team
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: blog-agent
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'BLOG_AGENT_VERSION', '1.0.0' );
define( 'BLOG_AGENT_FILE', __FILE__ );
define( 'BLOG_AGENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'BLOG_AGENT_URL', plugin_dir_url( __FILE__ ) );
define( 'BLOG_AGENT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Blog Agent Plugin Class
 */
final class Blog_Agent {

    /**
     * Single instance
     */
    private static ?Blog_Agent $instance = null;

    /**
     * Plugin components
     */
    public ?Blog_Agent_Settings $settings = null;
    public ?Blog_Agent_Projects $projects = null;
    public ?Blog_Agent_Generator $generator = null;
    public ?Blog_Agent_QA $qa = null;
    public ?Blog_Agent_Autopost $autopost = null;
    public ?Blog_Agent_Export $export = null;

    /**
     * Get singleton instance
     */
    public static function instance(): Blog_Agent {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies(): void {
        // Traits
        require_once BLOG_AGENT_PATH . 'includes/traits/trait-encryption.php';

        // API clients
        require_once BLOG_AGENT_PATH . 'includes/api/interface-provider-client.php';
        require_once BLOG_AGENT_PATH . 'includes/api/class-openai-client.php';
        require_once BLOG_AGENT_PATH . 'includes/api/class-gemini-client.php';
        require_once BLOG_AGENT_PATH . 'includes/api/class-api-factory.php';

        // Core classes
        require_once BLOG_AGENT_PATH . 'includes/class-settings.php';
        require_once BLOG_AGENT_PATH . 'includes/class-projects.php';
        require_once BLOG_AGENT_PATH . 'includes/class-generator.php';
        require_once BLOG_AGENT_PATH . 'includes/class-qa.php';
        require_once BLOG_AGENT_PATH . 'includes/class-autopost.php';
        require_once BLOG_AGENT_PATH . 'includes/class-export.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        register_activation_hook( BLOG_AGENT_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( BLOG_AGENT_FILE, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_action( 'init', [ $this, 'load_textdomain' ] );
    }

    /**
     * Initialize plugin components
     */
    public function init(): void {
        $this->settings  = new Blog_Agent_Settings();
        $this->projects  = new Blog_Agent_Projects();
        $this->generator = new Blog_Agent_Generator();
        $this->qa        = new Blog_Agent_QA();
        $this->autopost  = new Blog_Agent_Autopost();
        $this->export    = new Blog_Agent_Export();
    }

    /**
     * Load text domain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'blog-agent',
            false,
            dirname( BLOG_AGENT_BASENAME ) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        // Set default options
        $defaults = [
            'blog_agent_provider'           => 'openai',
            'blog_agent_openai_api_key'     => '',
            'blog_agent_gemini_api_key'     => '',
            'blog_agent_model'              => 'gpt-4o-mini',
            'blog_agent_temperature'        => 0.7,
            'blog_agent_max_tokens'         => 4000,
            'blog_agent_timeout'            => 60,
            'blog_agent_autopost_enabled'   => false,
            'blog_agent_autopost_mode'      => 'draft',
            'blog_agent_autopost_condition' => 'qa_approved',
            'blog_agent_schedule_delay'     => 60,
            'blog_agent_daily_limit'        => 10,
            'blog_agent_post_interval'      => 60,
            'blog_agent_forbidden_words'    => "必ず\n確実に\n絶対に\n100%",
            'blog_agent_retry_count'        => 0,
            'blog_agent_today_post_count'   => 0,
            'blog_agent_last_post_date'     => '',
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }

        // Create custom tables if needed
        $this->create_tables();

        // Schedule cron events
        if ( ! wp_next_scheduled( 'blog_agent_process_queue' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'blog_agent_process_queue' );
        }

        if ( ! wp_next_scheduled( 'blog_agent_daily_reset' ) ) {
            wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'blog_agent_daily_reset' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        wp_clear_scheduled_hook( 'blog_agent_process_queue' );
        wp_clear_scheduled_hook( 'blog_agent_daily_reset' );
        flush_rewrite_rules();
    }

    /**
     * Create custom tables
     */
    private function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Queue table for async jobs
        $table_name = $wpdb->prefix . 'blog_agent_queue';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(50) NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            project_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            data longtext,
            attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
            scheduled_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY job_type (job_type),
            KEY scheduled_at (scheduled_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

// Add custom cron schedule
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_five_minutes'] = [
        'interval' => 300,
        'display'  => __( 'Every 5 Minutes', 'blog-agent' ),
    ];
    return $schedules;
});

/**
 * Get Blog Agent instance
 */
function blog_agent(): Blog_Agent {
    return Blog_Agent::instance();
}

// Initialize
blog_agent();
