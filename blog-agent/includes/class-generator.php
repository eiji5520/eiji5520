<?php
/**
 * Article Generator Class
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_Generator {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'wp_ajax_blog_agent_generate_article', [ $this, 'ajax_generate_article' ] );
        add_action( 'wp_ajax_blog_agent_queue_generation', [ $this, 'ajax_queue_generation' ] );
        add_action( 'blog_agent_process_queue', [ $this, 'process_queue' ] );
    }

    /**
     * Add submenu page
     */
    public function add_menu(): void {
        add_submenu_page(
            'blog-agent',
            __( 'Generate Articles', 'blog-agent' ),
            __( 'Generate', 'blog-agent' ),
            'manage_options',
            'blog-agent-generator',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Render generator page
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require BLOG_AGENT_PATH . 'admin/views/generator.php';
    }

    /**
     * AJAX: Queue generation jobs
     */
    public function ajax_queue_generation(): void {
        check_ajax_referer( 'blog_agent_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'blog-agent' ) );
        }

        $project_id = absint( $_POST['project_id'] ?? 0 );
        $indices    = isset( $_POST['indices'] ) ? array_map( 'absint', (array) $_POST['indices'] ) : [];

        if ( ! $project_id ) {
            wp_send_json_error( __( 'Invalid project ID.', 'blog-agent' ) );
        }

        $project_data = blog_agent()->projects->get_project_data( $project_id );
        if ( empty( $project_data['plan'] ) ) {
            wp_send_json_error( __( 'Project has no plan. Generate a plan first.', 'blog-agent' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';
        $queued     = 0;

        foreach ( $indices as $index ) {
            if ( ! isset( $project_data['plan'][ $index ] ) ) {
                continue;
            }

            $article_plan = $project_data['plan'][ $index ];

            // Check if already queued or generated
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE project_id = %d AND data LIKE %s AND status IN ('pending', 'processing')",
                $project_id,
                '%"index":' . $index . '%'
            ) );

            if ( $existing ) {
                continue;
            }

            // Add to queue
            $wpdb->insert( $table_name, [
                'job_type'   => 'generate',
                'project_id' => $project_id,
                'status'     => 'pending',
                'data'       => wp_json_encode( [
                    'index'        => $index,
                    'article_plan' => $article_plan,
                    'project_data' => $project_data,
                ] ),
            ] );

            $queued++;
        }

        if ( $queued === 0 ) {
            wp_send_json_error( __( 'No articles to queue. They may already be queued or generated.', 'blog-agent' ) );
        }

        wp_send_json_success( [
            'message' => sprintf( __( '%d articles queued for generation.', 'blog-agent' ), $queued ),
        ] );
    }

    /**
     * AJAX: Generate single article immediately
     */
    public function ajax_generate_article(): void {
        check_ajax_referer( 'blog_agent_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'blog-agent' ) );
        }

        $project_id = absint( $_POST['project_id'] ?? 0 );
        $index      = absint( $_POST['index'] ?? 0 );

        if ( ! $project_id ) {
            wp_send_json_error( __( 'Invalid project ID.', 'blog-agent' ) );
        }

        $project_data = blog_agent()->projects->get_project_data( $project_id );
        if ( empty( $project_data['plan'][ $index ] ) ) {
            wp_send_json_error( __( 'Article plan not found.', 'blog-agent' ) );
        }

        $result = $this->generate_article(
            $project_id,
            $index,
            $project_data['plan'][ $index ],
            $project_data
        );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['error'] );
        }
    }

    /**
     * Process queued jobs
     */
    public function process_queue(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';

        // Get pending jobs
        $jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE status = 'pending'
             AND (scheduled_at IS NULL OR scheduled_at <= %s)
             ORDER BY created_at ASC
             LIMIT 5",
            current_time( 'mysql' )
        ) );

        foreach ( $jobs as $job ) {
            $this->process_job( $job );
        }
    }

    /**
     * Process a single job
     */
    private function process_job( object $job ): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';

        // Mark as processing
        $wpdb->update(
            $table_name,
            [ 'status' => 'processing', 'attempts' => $job->attempts + 1 ],
            [ 'id' => $job->id ]
        );

        $data = json_decode( $job->data, true );

        try {
            switch ( $job->job_type ) {
                case 'generate':
                    $result = $this->generate_article(
                        $job->project_id,
                        $data['index'],
                        $data['article_plan'],
                        $data['project_data']
                    );
                    break;

                case 'autopost':
                    $result = blog_agent()->autopost->process_autopost_job( $job->post_id );
                    break;

                default:
                    $result = [ 'success' => false, 'error' => 'Unknown job type' ];
            }

            if ( $result['success'] ) {
                $wpdb->update(
                    $table_name,
                    [
                        'status'  => 'completed',
                        'post_id' => $result['post_id'] ?? $job->post_id,
                    ],
                    [ 'id' => $job->id ]
                );
            } else {
                $this->handle_job_failure( $job, $result['error'] ?? 'Unknown error' );
            }
        } catch ( Exception $e ) {
            $this->handle_job_failure( $job, $e->getMessage() );
        }
    }

    /**
     * Handle job failure
     */
    private function handle_job_failure( object $job, string $error ): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';

        $max_attempts = 2;

        if ( $job->attempts >= $max_attempts ) {
            // Mark as failed
            $wpdb->update(
                $table_name,
                [ 'status' => 'failed' ],
                [ 'id' => $job->id ]
            );

            // Update retry count and possibly stop autopost
            $retry_count = (int) get_option( 'blog_agent_retry_count', 0 ) + 1;
            update_option( 'blog_agent_retry_count', $retry_count );

            // Log error
            update_option( 'blog_agent_last_error', [
                'message' => $error,
                'job_id'  => $job->id,
                'time'    => current_time( 'mysql' ),
            ] );
        } else {
            // Retry later
            $wpdb->update(
                $table_name,
                [
                    'status'       => 'pending',
                    'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + 300 ), // Retry in 5 minutes
                ],
                [ 'id' => $job->id ]
            );
        }
    }

    /**
     * Generate a single article
     */
    public function generate_article( int $project_id, int $index, array $article_plan, array $project_data ): array {
        // Build prompt
        $prompt = $this->build_article_prompt( $article_plan, $project_data );

        // Call AI
        $client = Blog_Agent_API_Factory::get_client();
        $result = $client->generate( $prompt );

        if ( ! $result['success'] ) {
            return [
                'success' => false,
                'error'   => $result['error'],
            ];
        }

        // Parse content
        $parsed = $this->parse_article_content( $result['content'] );

        // Create post
        $post_data = [
            'post_title'   => $parsed['title'] ?: ( $article_plan['title'] ?? __( 'Untitled', 'blog-agent' ) ),
            'post_content' => $parsed['content'],
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        ];

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return [
                'success' => false,
                'error'   => $post_id->get_error_message(),
            ];
        }

        // Save meta data
        update_post_meta( $post_id, '_blog_agent_project_id', $project_id );
        update_post_meta( $post_id, '_blog_agent_plan_index', $index );
        update_post_meta( $post_id, '_blog_agent_outline', wp_json_encode( $article_plan ) );
        update_post_meta( $post_id, '_blog_agent_meta_description', $parsed['meta_description'] ?? '' );
        update_post_meta( $post_id, '_blog_agent_tags_suggest', $parsed['tags'] ?? '' );
        update_post_meta( $post_id, '_blog_agent_qa_status', 'pending' );
        update_post_meta( $post_id, '_blog_agent_approval_status', 'draft' );
        update_post_meta( $post_id, '_blog_agent_generated_at', current_time( 'mysql' ) );

        // Run QA automatically
        blog_agent()->qa->run_qa( $post_id );

        // Queue autopost if enabled
        if ( get_option( 'blog_agent_autopost_enabled', false ) ) {
            $this->queue_autopost( $post_id );
        }

        return [
            'success' => true,
            'post_id' => $post_id,
            'title'   => $post_data['post_title'],
            'message' => sprintf(
                __( 'Article "%s" created successfully.', 'blog-agent' ),
                $post_data['post_title']
            ),
        ];
    }

    /**
     * Build article generation prompt
     */
    private function build_article_prompt( array $article_plan, array $project_data ): string {
        $prompt = "あなたはプロのブログライターです。以下の構成に従って、高品質なブログ記事を作成してください。\n\n";

        $prompt .= "【記事タイトル】\n{$article_plan['title']}\n\n";

        if ( ! empty( $article_plan['outline'] ) ) {
            $prompt .= "【見出し構成】\n{$article_plan['outline']}\n\n";
        }

        if ( ! empty( $article_plan['unique_angle'] ) ) {
            $prompt .= "【独自性のポイント】\n{$article_plan['unique_angle']}\n\n";
        }

        $prompt .= "【条件】\n";
        $prompt .= "- ニッチ/テーマ: {$project_data['niche']}\n";
        $prompt .= "- トーン: {$project_data['tone']}\n";
        $prompt .= "- 最低文字数: {$project_data['min_words']}文字\n";

        if ( ! empty( $project_data['persona'] ) ) {
            $prompt .= "- ターゲット読者: {$project_data['persona']}\n";
        }

        if ( ! empty( $article_plan['target_keyword'] ) ) {
            $prompt .= "- メインキーワード: {$article_plan['target_keyword']}\n";
        }

        $prompt .= "\n【出力形式】\n";
        $prompt .= "以下の形式で出力してください：\n\n";
        $prompt .= "---META_DESCRIPTION---\n";
        $prompt .= "（160文字以内のメタディスクリプション）\n\n";
        $prompt .= "---TAGS---\n";
        $prompt .= "（カンマ区切りのタグ候補）\n\n";
        $prompt .= "---CONTENT---\n";
        $prompt .= "（記事本文 - HTML形式で見出しにh2, h3を使用）\n";

        return $prompt;
    }

    /**
     * Parse article content from AI response
     */
    private function parse_article_content( string $content ): array {
        $result = [
            'title'            => '',
            'meta_description' => '',
            'tags'             => '',
            'content'          => '',
        ];

        // Extract meta description
        if ( preg_match( '/---META_DESCRIPTION---\s*(.*?)\s*(?=---|\z)/s', $content, $matches ) ) {
            $result['meta_description'] = trim( $matches[1] );
        }

        // Extract tags
        if ( preg_match( '/---TAGS---\s*(.*?)\s*(?=---|\z)/s', $content, $matches ) ) {
            $result['tags'] = trim( $matches[1] );
        }

        // Extract content
        if ( preg_match( '/---CONTENT---\s*(.*)/s', $content, $matches ) ) {
            $result['content'] = trim( $matches[1] );
        } else {
            // If no markers found, use entire content
            $result['content'] = $content;
        }

        // Extract title from content if present
        if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/i', $result['content'], $matches ) ) {
            $result['title'] = strip_tags( $matches[1] );
            // Remove h1 from content
            $result['content'] = preg_replace( '/<h1[^>]*>.*?<\/h1>/i', '', $result['content'], 1 );
        }

        // Clean up content
        $result['content'] = $this->clean_content( $result['content'] );

        return $result;
    }

    /**
     * Clean up generated content
     */
    private function clean_content( string $content ): string {
        // Remove markdown code blocks if present
        $content = preg_replace( '/```html?\s*/i', '', $content );
        $content = preg_replace( '/```\s*/', '', $content );

        // Ensure proper paragraph tags
        $content = wpautop( $content );

        return trim( $content );
    }

    /**
     * Queue autopost job
     */
    private function queue_autopost( int $post_id ): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';

        // Calculate scheduled time based on interval
        $interval     = (int) get_option( 'blog_agent_post_interval', 60 );
        $last_autopost = get_option( 'blog_agent_last_autopost_time', 0 );
        $scheduled_at  = max( time(), $last_autopost + ( $interval * 60 ) );

        $wpdb->insert( $table_name, [
            'job_type'     => 'autopost',
            'post_id'      => $post_id,
            'status'       => 'pending',
            'scheduled_at' => gmdate( 'Y-m-d H:i:s', $scheduled_at ),
            'data'         => wp_json_encode( [ 'post_id' => $post_id ] ),
        ] );
    }

    /**
     * Get queue status for a project
     */
    public function get_project_queue_status( int $project_id ): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blog_agent_queue';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE project_id = %d ORDER BY created_at DESC",
            $project_id
        ) );
    }
}
