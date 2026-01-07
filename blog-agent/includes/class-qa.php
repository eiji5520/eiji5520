<?php
/**
 * Quality Assurance Class
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_QA {

    /**
     * QA Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PASSED  = 'passed';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAILED  = 'failed';

    /**
     * Warning types
     */
    public const WARNING_FORBIDDEN_WORD = 'forbidden_word';
    public const WARNING_ASSERTION      = 'assertion';
    public const WARNING_SIMILARITY     = 'similarity';
    public const WARNING_SHORT_CONTENT  = 'short_content';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'wp_ajax_blog_agent_run_qa', [ $this, 'ajax_run_qa' ] );
        add_action( 'wp_ajax_blog_agent_approve_article', [ $this, 'ajax_approve_article' ] );
        add_filter( 'manage_posts_columns', [ $this, 'add_qa_column' ] );
        add_action( 'manage_posts_custom_column', [ $this, 'render_qa_column' ], 10, 2 );
    }

    /**
     * Add submenu page
     */
    public function add_menu(): void {
        add_submenu_page(
            'blog-agent',
            __( 'QA Review', 'blog-agent' ),
            __( 'QA Review', 'blog-agent' ),
            'manage_options',
            'blog-agent-qa',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes(): void {
        // Only add to posts that have the blog agent meta
        add_meta_box(
            'blog_agent_qa_results',
            __( 'Blog Agent QA', 'blog-agent' ),
            [ $this, 'render_qa_meta_box' ],
            'post',
            'side',
            'high'
        );
    }

    /**
     * Render QA meta box
     */
    public function render_qa_meta_box( WP_Post $post ): void {
        $project_id = get_post_meta( $post->ID, '_blog_agent_project_id', true );

        if ( ! $project_id ) {
            echo '<p>' . esc_html__( 'Not a Blog Agent article.', 'blog-agent' ) . '</p>';
            return;
        }

        $qa_status     = get_post_meta( $post->ID, '_blog_agent_qa_status', true ) ?: self::STATUS_PENDING;
        $qa_result     = get_post_meta( $post->ID, '_blog_agent_qa_result', true );
        $qa_result     = is_array( $qa_result ) ? $qa_result : [];
        $approval      = get_post_meta( $post->ID, '_blog_agent_approval_status', true ) ?: 'draft';

        ?>
        <div class="blog-agent-qa-box">
            <!-- QA Status -->
            <div class="qa-status-section">
                <p>
                    <strong><?php esc_html_e( 'QA Status:', 'blog-agent' ); ?></strong>
                    <span class="qa-status qa-<?php echo esc_attr( $qa_status ); ?>">
                        <?php echo esc_html( $this->get_status_label( $qa_status ) ); ?>
                    </span>
                </p>
                <button type="button" class="button button-small run-qa-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Re-run QA', 'blog-agent' ); ?>
                </button>
            </div>

            <!-- QA Warnings -->
            <?php if ( ! empty( $qa_result['warnings'] ) ) : ?>
            <div class="qa-warnings">
                <h4><?php esc_html_e( 'Warnings', 'blog-agent' ); ?></h4>
                <ul>
                    <?php foreach ( $qa_result['warnings'] as $warning ) : ?>
                        <li class="warning-<?php echo esc_attr( $warning['type'] ); ?>">
                            <span class="warning-icon">⚠️</span>
                            <?php echo esc_html( $warning['message'] ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- QA Stats -->
            <?php if ( ! empty( $qa_result['stats'] ) ) : ?>
            <div class="qa-stats">
                <h4><?php esc_html_e( 'Stats', 'blog-agent' ); ?></h4>
                <ul>
                    <li><?php printf( esc_html__( 'Word Count: %d', 'blog-agent' ), $qa_result['stats']['word_count'] ?? 0 ); ?></li>
                    <li><?php printf( esc_html__( 'Character Count: %d', 'blog-agent' ), $qa_result['stats']['char_count'] ?? 0 ); ?></li>
                    <?php if ( isset( $qa_result['stats']['similarity_score'] ) ) : ?>
                        <li><?php printf( esc_html__( 'Max Similarity: %.1f%%', 'blog-agent' ), $qa_result['stats']['similarity_score'] * 100 ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <hr />

            <!-- Approval Status -->
            <div class="approval-section">
                <p>
                    <strong><?php esc_html_e( 'Approval:', 'blog-agent' ); ?></strong>
                    <span class="approval-status approval-<?php echo esc_attr( $approval ); ?>">
                        <?php echo esc_html( ucfirst( $approval ) ); ?>
                    </span>
                </p>

                <?php if ( $approval !== 'approved' && current_user_can( 'manage_options' ) ) : ?>
                    <button type="button"
                            class="button button-primary approve-article-btn"
                            data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php esc_html_e( 'Approve Article', 'blog-agent' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <span class="qa-result-message"></span>
        </div>
        <?php
    }

    /**
     * Render QA review page
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require BLOG_AGENT_PATH . 'admin/views/qa.php';
    }

    /**
     * Run QA checks on a post
     */
    public function run_qa( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [
                'success' => false,
                'error'   => __( 'Post not found.', 'blog-agent' ),
            ];
        }

        $content  = wp_strip_all_tags( $post->post_content );
        $warnings = [];
        $stats    = [];

        // Word and character count
        $stats['word_count'] = str_word_count( $content );
        $stats['char_count'] = mb_strlen( $content );

        // Check minimum word count
        $project_id = get_post_meta( $post_id, '_blog_agent_project_id', true );
        $min_words  = 1500;
        if ( $project_id ) {
            $min_words = (int) get_post_meta( $project_id, '_blog_agent_min_words', true ) ?: 1500;
        }

        if ( $stats['word_count'] < $min_words * 0.8 ) { // Allow 20% margin
            $warnings[] = [
                'type'    => self::WARNING_SHORT_CONTENT,
                'message' => sprintf(
                    __( 'Content is shorter than expected. Word count: %d (expected: %d+)', 'blog-agent' ),
                    $stats['word_count'],
                    $min_words
                ),
                'severity' => 'medium',
            ];
        }

        // Check forbidden words
        $forbidden_words = $this->get_forbidden_words();
        foreach ( $forbidden_words as $word ) {
            if ( mb_stripos( $content, $word ) !== false ) {
                $warnings[] = [
                    'type'    => self::WARNING_FORBIDDEN_WORD,
                    'message' => sprintf( __( 'Contains forbidden word: "%s"', 'blog-agent' ), $word ),
                    'severity' => 'high',
                ];
            }
        }

        // Check assertion words
        $assertion_words = [ '必ず', '確実に', '絶対に', '100%', '間違いなく', '完全に' ];
        foreach ( $assertion_words as $word ) {
            if ( mb_stripos( $content, $word ) !== false ) {
                $warnings[] = [
                    'type'    => self::WARNING_ASSERTION,
                    'message' => sprintf( __( 'Contains assertion word: "%s" - consider softening', 'blog-agent' ), $word ),
                    'severity' => 'low',
                ];
            }
        }

        // Check similarity with existing posts
        $similarity_result = $this->check_similarity( $post_id, $content );
        if ( $similarity_result['max_score'] > 0.7 ) {
            $warnings[] = [
                'type'    => self::WARNING_SIMILARITY,
                'message' => sprintf(
                    __( 'High similarity (%.1f%%) with: %s', 'blog-agent' ),
                    $similarity_result['max_score'] * 100,
                    $similarity_result['similar_title']
                ),
                'severity' => 'high',
            ];
        }
        $stats['similarity_score'] = $similarity_result['max_score'];

        // Determine overall status
        $status = self::STATUS_PASSED;
        $high_severity_count = 0;

        foreach ( $warnings as $warning ) {
            if ( $warning['severity'] === 'high' ) {
                $high_severity_count++;
            }
        }

        if ( $high_severity_count > 0 ) {
            $status = self::STATUS_FAILED;
        } elseif ( ! empty( $warnings ) ) {
            $status = self::STATUS_WARNING;
        }

        // Save results
        $result = [
            'warnings' => $warnings,
            'stats'    => $stats,
            'checked_at' => current_time( 'mysql' ),
        ];

        update_post_meta( $post_id, '_blog_agent_qa_status', $status );
        update_post_meta( $post_id, '_blog_agent_qa_result', $result );

        return [
            'success' => true,
            'status'  => $status,
            'result'  => $result,
        ];
    }

    /**
     * Check similarity with existing posts using N-gram Jaccard
     */
    private function check_similarity( int $post_id, string $content ): array {
        $result = [
            'max_score'     => 0,
            'similar_title' => '',
            'similar_id'    => 0,
        ];

        // Get N-grams for current content
        $current_ngrams = $this->get_ngrams( $content, 3 );

        if ( empty( $current_ngrams ) ) {
            return $result;
        }

        // Get recent published posts
        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'exclude'        => [ $post_id ],
        ] );

        foreach ( $posts as $post ) {
            $post_content = wp_strip_all_tags( $post->post_content );
            $post_ngrams  = $this->get_ngrams( $post_content, 3 );

            if ( empty( $post_ngrams ) ) {
                continue;
            }

            // Calculate Jaccard similarity
            $intersection = count( array_intersect( $current_ngrams, $post_ngrams ) );
            $union        = count( array_unique( array_merge( $current_ngrams, $post_ngrams ) ) );

            if ( $union > 0 ) {
                $score = $intersection / $union;
                if ( $score > $result['max_score'] ) {
                    $result['max_score']     = $score;
                    $result['similar_title'] = $post->post_title;
                    $result['similar_id']    = $post->ID;
                }
            }
        }

        return $result;
    }

    /**
     * Get N-grams from text
     */
    private function get_ngrams( string $text, int $n = 3 ): array {
        // Remove extra whitespace
        $text = preg_replace( '/\s+/', '', $text );
        $text = mb_strtolower( $text );

        $ngrams = [];
        $length = mb_strlen( $text );

        for ( $i = 0; $i <= $length - $n; $i++ ) {
            $ngrams[] = mb_substr( $text, $i, $n );
        }

        return array_unique( $ngrams );
    }

    /**
     * Get forbidden words list
     */
    private function get_forbidden_words(): array {
        $words_text = get_option( 'blog_agent_forbidden_words', '' );
        $words      = preg_split( '/[\r\n,]+/', $words_text );
        return array_filter( array_map( 'trim', $words ) );
    }

    /**
     * Get status label
     */
    public function get_status_label( string $status ): string {
        return match ( $status ) {
            self::STATUS_PASSED  => __( 'Passed', 'blog-agent' ),
            self::STATUS_WARNING => __( 'Warning', 'blog-agent' ),
            self::STATUS_FAILED  => __( 'Failed', 'blog-agent' ),
            default              => __( 'Pending', 'blog-agent' ),
        };
    }

    /**
     * AJAX: Run QA
     */
    public function ajax_run_qa(): void {
        check_ajax_referer( 'blog_agent_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'blog-agent' ) );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID.', 'blog-agent' ) );
        }

        $result = $this->run_qa( $post_id );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['error'] );
        }
    }

    /**
     * AJAX: Approve article
     */
    public function ajax_approve_article(): void {
        check_ajax_referer( 'blog_agent_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'blog-agent' ) );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID.', 'blog-agent' ) );
        }

        update_post_meta( $post_id, '_blog_agent_approval_status', 'approved' );
        update_post_meta( $post_id, '_blog_agent_approved_at', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_blog_agent_approved_by', get_current_user_id() );

        // Trigger autopost check if enabled
        if ( get_option( 'blog_agent_autopost_enabled', false ) ) {
            do_action( 'blog_agent_article_approved', $post_id );
        }

        wp_send_json_success( __( 'Article approved successfully.', 'blog-agent' ) );
    }

    /**
     * Add QA column to posts list
     */
    public function add_qa_column( array $columns ): array {
        $columns['blog_agent_qa'] = __( 'Blog Agent QA', 'blog-agent' );
        return $columns;
    }

    /**
     * Render QA column content
     */
    public function render_qa_column( string $column, int $post_id ): void {
        if ( $column !== 'blog_agent_qa' ) {
            return;
        }

        $project_id = get_post_meta( $post_id, '_blog_agent_project_id', true );
        if ( ! $project_id ) {
            echo '-';
            return;
        }

        $qa_status = get_post_meta( $post_id, '_blog_agent_qa_status', true ) ?: self::STATUS_PENDING;
        $approval  = get_post_meta( $post_id, '_blog_agent_approval_status', true ) ?: 'draft';

        echo '<span class="qa-status qa-' . esc_attr( $qa_status ) . '">' . esc_html( $this->get_status_label( $qa_status ) ) . '</span>';
        echo ' / ';
        echo '<span class="approval-status approval-' . esc_attr( $approval ) . '">' . esc_html( ucfirst( $approval ) ) . '</span>';
    }

    /**
     * Check if article passes QA for autopost
     */
    public function passes_qa_for_autopost( int $post_id ): bool {
        $qa_status = get_post_meta( $post_id, '_blog_agent_qa_status', true );

        // Passed or Warning (minor warnings) are acceptable
        return in_array( $qa_status, [ self::STATUS_PASSED, self::STATUS_WARNING ], true );
    }
}
