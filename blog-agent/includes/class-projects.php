<?php
/**
 * Projects Custom Post Type
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_Projects {

    /**
     * Post type name
     */
    public const POST_TYPE = 'blog_agent_project';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_meta' ] );
        add_action( 'wp_ajax_blog_agent_generate_plan', [ $this, 'ajax_generate_plan' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'custom_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
    }

    /**
     * Register custom post type
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => __( 'Projects', 'blog-agent' ),
            'singular_name'         => __( 'Project', 'blog-agent' ),
            'menu_name'             => __( 'Projects', 'blog-agent' ),
            'add_new'               => __( 'Add New', 'blog-agent' ),
            'add_new_item'          => __( 'Add New Project', 'blog-agent' ),
            'edit_item'             => __( 'Edit Project', 'blog-agent' ),
            'new_item'              => __( 'New Project', 'blog-agent' ),
            'view_item'             => __( 'View Project', 'blog-agent' ),
            'search_items'          => __( 'Search Projects', 'blog-agent' ),
            'not_found'             => __( 'No projects found', 'blog-agent' ),
            'not_found_in_trash'    => __( 'No projects found in Trash', 'blog-agent' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'blog-agent',
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => [ 'title' ],
        ];

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'blog_agent_project_settings',
            __( 'Project Settings', 'blog-agent' ),
            [ $this, 'render_settings_meta_box' ],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'blog_agent_project_plan',
            __( 'Generated Plan', 'blog-agent' ),
            [ $this, 'render_plan_meta_box' ],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'blog_agent_project_articles',
            __( 'Generated Articles', 'blog-agent' ),
            [ $this, 'render_articles_meta_box' ],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render settings meta box
     */
    public function render_settings_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'blog_agent_project_meta', 'blog_agent_project_nonce' );

        $niche       = get_post_meta( $post->ID, '_blog_agent_niche', true );
        $persona     = get_post_meta( $post->ID, '_blog_agent_persona', true );
        $intent      = get_post_meta( $post->ID, '_blog_agent_intent', true );
        $keywords    = get_post_meta( $post->ID, '_blog_agent_keywords', true );
        $tone        = get_post_meta( $post->ID, '_blog_agent_tone', true );
        $min_words   = get_post_meta( $post->ID, '_blog_agent_min_words', true ) ?: 1500;
        $count       = get_post_meta( $post->ID, '_blog_agent_count', true ) ?: 5;
        ?>
        <table class="form-table">
            <tr>
                <th><label for="blog_agent_niche"><?php esc_html_e( 'Niche / Topic', 'blog-agent' ); ?></label></th>
                <td>
                    <input type="text"
                           name="blog_agent_niche"
                           id="blog_agent_niche"
                           value="<?php echo esc_attr( $niche ); ?>"
                           class="regular-text" />
                    <p class="description"><?php esc_html_e( 'e.g., "Web development", "Cooking recipes", "Personal finance"', 'blog-agent' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="blog_agent_persona"><?php esc_html_e( 'Target Persona', 'blog-agent' ); ?></label></th>
                <td>
                    <textarea name="blog_agent_persona"
                              id="blog_agent_persona"
                              rows="3"
                              class="large-text"><?php echo esc_textarea( $persona ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Describe your target reader (age, interests, pain points, etc.)', 'blog-agent' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="blog_agent_intent"><?php esc_html_e( 'Search Intent', 'blog-agent' ); ?></label></th>
                <td>
                    <select name="blog_agent_intent" id="blog_agent_intent">
                        <option value="informational" <?php selected( $intent, 'informational' ); ?>><?php esc_html_e( 'Informational', 'blog-agent' ); ?></option>
                        <option value="commercial" <?php selected( $intent, 'commercial' ); ?>><?php esc_html_e( 'Commercial', 'blog-agent' ); ?></option>
                        <option value="transactional" <?php selected( $intent, 'transactional' ); ?>><?php esc_html_e( 'Transactional', 'blog-agent' ); ?></option>
                        <option value="navigational" <?php selected( $intent, 'navigational' ); ?>><?php esc_html_e( 'Navigational', 'blog-agent' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="blog_agent_keywords"><?php esc_html_e( 'Keywords', 'blog-agent' ); ?></label></th>
                <td>
                    <textarea name="blog_agent_keywords"
                              id="blog_agent_keywords"
                              rows="3"
                              class="large-text"><?php echo esc_textarea( $keywords ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Enter keywords, one per line or comma-separated.', 'blog-agent' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="blog_agent_tone"><?php esc_html_e( 'Tone / Style', 'blog-agent' ); ?></label></th>
                <td>
                    <select name="blog_agent_tone" id="blog_agent_tone">
                        <option value="professional" <?php selected( $tone, 'professional' ); ?>><?php esc_html_e( 'Professional', 'blog-agent' ); ?></option>
                        <option value="casual" <?php selected( $tone, 'casual' ); ?>><?php esc_html_e( 'Casual / Friendly', 'blog-agent' ); ?></option>
                        <option value="formal" <?php selected( $tone, 'formal' ); ?>><?php esc_html_e( 'Formal / Academic', 'blog-agent' ); ?></option>
                        <option value="conversational" <?php selected( $tone, 'conversational' ); ?>><?php esc_html_e( 'Conversational', 'blog-agent' ); ?></option>
                        <option value="humorous" <?php selected( $tone, 'humorous' ); ?>><?php esc_html_e( 'Humorous', 'blog-agent' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="blog_agent_min_words"><?php esc_html_e( 'Minimum Words', 'blog-agent' ); ?></label></th>
                <td>
                    <input type="number"
                           name="blog_agent_min_words"
                           id="blog_agent_min_words"
                           value="<?php echo esc_attr( $min_words ); ?>"
                           min="500"
                           max="10000"
                           class="small-text" />
                </td>
            </tr>
            <tr>
                <th><label for="blog_agent_count"><?php esc_html_e( 'Number of Articles', 'blog-agent' ); ?></label></th>
                <td>
                    <input type="number"
                           name="blog_agent_count"
                           id="blog_agent_count"
                           value="<?php echo esc_attr( $count ); ?>"
                           min="1"
                           max="50"
                           class="small-text" />
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" id="generate-plan" class="button button-secondary" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
                <?php esc_html_e( '✨ Generate Plan', 'blog-agent' ); ?>
            </button>
            <span id="plan-status"></span>
        </p>
        <?php
    }

    /**
     * Render plan meta box
     */
    public function render_plan_meta_box( WP_Post $post ): void {
        $plan = get_post_meta( $post->ID, '_blog_agent_plan', true );
        ?>
        <div id="blog-agent-plan-container">
            <?php if ( ! empty( $plan ) ) : ?>
                <div class="blog-agent-plan">
                    <?php
                    $plan_data = json_decode( $plan, true );
                    if ( is_array( $plan_data ) ) :
                        foreach ( $plan_data as $index => $article ) :
                    ?>
                        <div class="plan-article">
                            <h4><?php echo esc_html( ( $index + 1 ) . '. ' . ( $article['title'] ?? '' ) ); ?></h4>
                            <?php if ( ! empty( $article['outline'] ) ) : ?>
                                <div class="plan-outline">
                                    <?php echo wp_kses_post( nl2br( $article['outline'] ) ); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $article['unique_angle'] ) ) : ?>
                                <p><strong><?php esc_html_e( 'Unique Angle:', 'blog-agent' ); ?></strong> <?php echo esc_html( $article['unique_angle'] ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php
                        endforeach;
                    else :
                        echo '<pre>' . esc_html( $plan ) . '</pre>';
                    endif;
                    ?>
                </div>
            <?php else : ?>
                <p><?php esc_html_e( 'No plan generated yet. Click "Generate Plan" above.', 'blog-agent' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render articles meta box
     */
    public function render_articles_meta_box( WP_Post $post ): void {
        $articles = get_posts( [
            'post_type'      => 'post',
            'posts_per_page' => -1,
            'meta_key'       => '_blog_agent_project_id',
            'meta_value'     => $post->ID,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        if ( empty( $articles ) ) :
        ?>
            <p><?php esc_html_e( 'No articles generated yet.', 'blog-agent' ); ?></p>
        <?php else : ?>
            <ul class="blog-agent-article-list">
                <?php foreach ( $articles as $article ) : ?>
                    <li>
                        <a href="<?php echo esc_url( get_edit_post_link( $article->ID ) ); ?>">
                            <?php echo esc_html( $article->post_title ); ?>
                        </a>
                        <span class="post-status status-<?php echo esc_attr( $article->post_status ); ?>">
                            (<?php echo esc_html( get_post_status_object( $article->post_status )->label ); ?>)
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=blog-agent-generator&project_id=' . $post->ID ) ); ?>" class="button button-primary">
                <?php esc_html_e( 'Generate Articles', 'blog-agent' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Save meta data
     */
    public function save_meta( int $post_id ): void {
        if ( ! isset( $_POST['blog_agent_project_nonce'] ) ||
             ! wp_verify_nonce( $_POST['blog_agent_project_nonce'], 'blog_agent_project_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            'blog_agent_niche'     => 'sanitize_text_field',
            'blog_agent_persona'   => 'sanitize_textarea_field',
            'blog_agent_intent'    => 'sanitize_text_field',
            'blog_agent_keywords'  => 'sanitize_textarea_field',
            'blog_agent_tone'      => 'sanitize_text_field',
            'blog_agent_min_words' => 'absint',
            'blog_agent_count'     => 'absint',
        ];

        foreach ( $fields as $field => $sanitizer ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = call_user_func( $sanitizer, $_POST[ $field ] );
                update_post_meta( $post_id, '_' . $field, $value );
            }
        }
    }

    /**
     * AJAX: Generate plan
     */
    public function ajax_generate_plan(): void {
        check_ajax_referer( 'blog_agent_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'blog-agent' ) );
        }

        $project_id = absint( $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) {
            wp_send_json_error( __( 'Invalid project ID.', 'blog-agent' ) );
        }

        $project = get_post( $project_id );
        if ( ! $project || $project->post_type !== self::POST_TYPE ) {
            wp_send_json_error( __( 'Project not found.', 'blog-agent' ) );
        }

        // Get project settings
        $niche     = get_post_meta( $project_id, '_blog_agent_niche', true );
        $persona   = get_post_meta( $project_id, '_blog_agent_persona', true );
        $intent    = get_post_meta( $project_id, '_blog_agent_intent', true );
        $keywords  = get_post_meta( $project_id, '_blog_agent_keywords', true );
        $tone      = get_post_meta( $project_id, '_blog_agent_tone', true );
        $min_words = get_post_meta( $project_id, '_blog_agent_min_words', true ) ?: 1500;
        $count     = get_post_meta( $project_id, '_blog_agent_count', true ) ?: 5;

        if ( empty( $niche ) ) {
            wp_send_json_error( __( 'Please fill in at least the Niche/Topic field.', 'blog-agent' ) );
        }

        // Build prompt
        $prompt = $this->build_plan_prompt( [
            'niche'     => $niche,
            'persona'   => $persona,
            'intent'    => $intent,
            'keywords'  => $keywords,
            'tone'      => $tone,
            'min_words' => $min_words,
            'count'     => $count,
        ] );

        // Call AI
        $client = Blog_Agent_API_Factory::get_client();
        $result = $client->generate( $prompt );

        if ( ! $result['success'] ) {
            wp_send_json_error( $result['error'] );
        }

        // Parse the response
        $plan = $this->parse_plan_response( $result['content'], $count );

        // Save plan
        update_post_meta( $project_id, '_blog_agent_plan', wp_json_encode( $plan ) );
        update_post_meta( $project_id, '_blog_agent_plan_raw', $result['content'] );

        wp_send_json_success( [
            'message' => __( 'Plan generated successfully!', 'blog-agent' ),
            'plan'    => $plan,
        ] );
    }

    /**
     * Build plan generation prompt
     */
    private function build_plan_prompt( array $settings ): string {
        $prompt = "あなたはSEOとコンテンツマーケティングの専門家です。以下の条件に基づいて、ブログ記事の企画プランを作成してください。\n\n";

        $prompt .= "【条件】\n";
        $prompt .= "- ニッチ/テーマ: {$settings['niche']}\n";

        if ( ! empty( $settings['persona'] ) ) {
            $prompt .= "- ターゲットペルソナ: {$settings['persona']}\n";
        }

        $prompt .= "- 検索意図: {$settings['intent']}\n";

        if ( ! empty( $settings['keywords'] ) ) {
            $prompt .= "- キーワード: {$settings['keywords']}\n";
        }

        $prompt .= "- トーン: {$settings['tone']}\n";
        $prompt .= "- 最低文字数: {$settings['min_words']}文字\n";
        $prompt .= "- 記事数: {$settings['count']}本\n\n";

        $prompt .= "【出力形式】\n";
        $prompt .= "各記事について以下の形式でJSON配列として出力してください:\n";
        $prompt .= "```json\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"title\": \"記事タイトル\",\n";
        $prompt .= "    \"outline\": \"H2/H3の見出し構成（改行区切り）\",\n";
        $prompt .= "    \"unique_angle\": \"この記事の独自性・差別化ポイント\",\n";
        $prompt .= "    \"target_keyword\": \"メインキーワード\",\n";
        $prompt .= "    \"suggested_elements\": \"挿入すべきパーツ（図解、事例、CTA等）\"\n";
        $prompt .= "  }\n";
        $prompt .= "]\n";
        $prompt .= "```\n\n";

        $prompt .= "必ずJSON形式で出力してください。";

        return $prompt;
    }

    /**
     * Parse plan response from AI
     */
    private function parse_plan_response( string $content, int $expected_count ): array {
        // Try to extract JSON from the response
        if ( preg_match( '/```json\s*(.*?)\s*```/s', $content, $matches ) ) {
            $json = $matches[1];
        } elseif ( preg_match( '/\[\s*\{.*\}\s*\]/s', $content, $matches ) ) {
            $json = $matches[0];
        } else {
            $json = $content;
        }

        $plan = json_decode( $json, true );

        if ( ! is_array( $plan ) ) {
            // If JSON parsing failed, create a basic structure
            return [ [
                'title'              => __( 'Untitled Article', 'blog-agent' ),
                'outline'            => $content,
                'unique_angle'       => '',
                'target_keyword'     => '',
                'suggested_elements' => '',
            ] ];
        }

        return $plan;
    }

    /**
     * Custom columns
     */
    public function custom_columns( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'title' === $key ) {
                $new_columns['niche']    = __( 'Niche', 'blog-agent' );
                $new_columns['articles'] = __( 'Articles', 'blog-agent' );
                $new_columns['plan']     = __( 'Plan', 'blog-agent' );
            }
        }
        return $new_columns;
    }

    /**
     * Column content
     */
    public function column_content( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'niche':
                echo esc_html( get_post_meta( $post_id, '_blog_agent_niche', true ) );
                break;
            case 'articles':
                $count = count( get_posts( [
                    'post_type'      => 'post',
                    'posts_per_page' => -1,
                    'meta_key'       => '_blog_agent_project_id',
                    'meta_value'     => $post_id,
                    'fields'         => 'ids',
                ] ) );
                echo esc_html( $count );
                break;
            case 'plan':
                $plan = get_post_meta( $post_id, '_blog_agent_plan', true );
                if ( ! empty( $plan ) ) {
                    $plan_data = json_decode( $plan, true );
                    $plan_count = is_array( $plan_data ) ? count( $plan_data ) : 0;
                    echo esc_html( sprintf( __( '%d articles planned', 'blog-agent' ), $plan_count ) );
                } else {
                    echo '<span class="dashicons dashicons-minus"></span>';
                }
                break;
        }
    }

    /**
     * Get project data
     */
    public function get_project_data( int $project_id ): array {
        return [
            'niche'     => get_post_meta( $project_id, '_blog_agent_niche', true ),
            'persona'   => get_post_meta( $project_id, '_blog_agent_persona', true ),
            'intent'    => get_post_meta( $project_id, '_blog_agent_intent', true ),
            'keywords'  => get_post_meta( $project_id, '_blog_agent_keywords', true ),
            'tone'      => get_post_meta( $project_id, '_blog_agent_tone', true ),
            'min_words' => get_post_meta( $project_id, '_blog_agent_min_words', true ) ?: 1500,
            'count'     => get_post_meta( $project_id, '_blog_agent_count', true ) ?: 5,
            'plan'      => json_decode( get_post_meta( $project_id, '_blog_agent_plan', true ), true ) ?: [],
        ];
    }
}
