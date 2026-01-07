<?php
/**
 * Export Class
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_Export {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_post_blog_agent_export', [ $this, 'handle_export' ] );
    }

    /**
     * Add submenu page
     */
    public function add_menu(): void {
        add_submenu_page(
            'blog-agent',
            __( 'Export', 'blog-agent' ),
            __( 'Export', 'blog-agent' ),
            'manage_options',
            'blog-agent-export',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Render export page
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require BLOG_AGENT_PATH . 'admin/views/export.php';
    }

    /**
     * Handle export request
     */
    public function handle_export(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'blog-agent' ) );
        }

        check_admin_referer( 'blog_agent_export', 'blog_agent_export_nonce' );

        $format     = sanitize_text_field( $_POST['export_format'] ?? 'csv' );
        $project_id = absint( $_POST['project_id'] ?? 0 );
        $status     = sanitize_text_field( $_POST['post_status'] ?? 'any' );

        // Get posts
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_blog_agent_project_id',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        if ( $project_id ) {
            $args['meta_query'][] = [
                'key'   => '_blog_agent_project_id',
                'value' => $project_id,
            ];
        }

        if ( $status !== 'any' ) {
            $args['post_status'] = $status;
        } else {
            $args['post_status'] = [ 'publish', 'draft', 'future', 'pending' ];
        }

        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            wp_redirect( add_query_arg( 'export_error', 'no_posts', admin_url( 'admin.php?page=blog-agent-export' ) ) );
            exit;
        }

        switch ( $format ) {
            case 'markdown':
                $this->export_markdown( $posts );
                break;
            case 'html':
                $this->export_html( $posts );
                break;
            case 'csv':
            default:
                $this->export_csv( $posts );
                break;
        }
    }

    /**
     * Export as CSV
     */
    private function export_csv( array $posts ): void {
        $filename = 'blog-agent-export-' . gmdate( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' );

        // BOM for Excel UTF-8 compatibility
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Header row
        fputcsv( $output, [
            'ID',
            'Title',
            'Status',
            'QA Status',
            'Approval Status',
            'Project ID',
            'Meta Description',
            'Tags',
            'Word Count',
            'Created',
            'Modified',
            'URL',
        ] );

        foreach ( $posts as $post ) {
            $qa_result = get_post_meta( $post->ID, '_blog_agent_qa_result', true );
            $word_count = $qa_result['stats']['word_count'] ?? str_word_count( wp_strip_all_tags( $post->post_content ) );

            fputcsv( $output, [
                $post->ID,
                $post->post_title,
                $post->post_status,
                get_post_meta( $post->ID, '_blog_agent_qa_status', true ) ?: 'pending',
                get_post_meta( $post->ID, '_blog_agent_approval_status', true ) ?: 'draft',
                get_post_meta( $post->ID, '_blog_agent_project_id', true ),
                get_post_meta( $post->ID, '_blog_agent_meta_description', true ),
                get_post_meta( $post->ID, '_blog_agent_tags_suggest', true ),
                $word_count,
                $post->post_date,
                $post->post_modified,
                get_permalink( $post->ID ),
            ] );
        }

        fclose( $output );
        exit;
    }

    /**
     * Export as Markdown (ZIP)
     */
    private function export_markdown( array $posts ): void {
        $zip = new ZipArchive();
        $filename = 'blog-agent-export-' . gmdate( 'Y-m-d-His' ) . '.zip';
        $tmp_file = wp_tempnam( $filename );

        if ( $zip->open( $tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            wp_die( __( 'Failed to create ZIP file.', 'blog-agent' ) );
        }

        foreach ( $posts as $index => $post ) {
            $markdown = $this->post_to_markdown( $post );
            $safe_title = sanitize_file_name( $post->post_title );
            $file_name = sprintf( '%03d-%s.md', $index + 1, $safe_title );
            $zip->addFromString( $file_name, $markdown );
        }

        // Add index file
        $index_content = $this->create_index_markdown( $posts );
        $zip->addFromString( 'index.md', $index_content );

        $zip->close();

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $tmp_file ) );

        readfile( $tmp_file );
        unlink( $tmp_file );
        exit;
    }

    /**
     * Export as HTML (ZIP)
     */
    private function export_html( array $posts ): void {
        $zip = new ZipArchive();
        $filename = 'blog-agent-export-' . gmdate( 'Y-m-d-His' ) . '.zip';
        $tmp_file = wp_tempnam( $filename );

        if ( $zip->open( $tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            wp_die( __( 'Failed to create ZIP file.', 'blog-agent' ) );
        }

        foreach ( $posts as $index => $post ) {
            $html = $this->post_to_html( $post );
            $safe_title = sanitize_file_name( $post->post_title );
            $file_name = sprintf( '%03d-%s.html', $index + 1, $safe_title );
            $zip->addFromString( $file_name, $html );
        }

        // Add index file
        $index_content = $this->create_index_html( $posts );
        $zip->addFromString( 'index.html', $index_content );

        $zip->close();

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $tmp_file ) );

        readfile( $tmp_file );
        unlink( $tmp_file );
        exit;
    }

    /**
     * Convert post to Markdown
     */
    private function post_to_markdown( WP_Post $post ): string {
        $meta_desc = get_post_meta( $post->ID, '_blog_agent_meta_description', true );
        $tags      = get_post_meta( $post->ID, '_blog_agent_tags_suggest', true );

        $markdown = "# {$post->post_title}\n\n";

        if ( $meta_desc ) {
            $markdown .= "> {$meta_desc}\n\n";
        }

        if ( $tags ) {
            $markdown .= "**Tags:** {$tags}\n\n";
        }

        $markdown .= "---\n\n";

        // Convert HTML to Markdown (basic conversion)
        $content = $post->post_content;
        $content = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/i', "\n## $1\n", $content );
        $content = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/i', "\n### $1\n", $content );
        $content = preg_replace( '/<h4[^>]*>(.*?)<\/h4>/i', "\n#### $1\n", $content );
        $content = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $content );
        $content = preg_replace( '/<strong[^>]*>(.*?)<\/strong>/i', "**$1**", $content );
        $content = preg_replace( '/<em[^>]*>(.*?)<\/em>/i', "*$1*", $content );
        $content = preg_replace( '/<ul[^>]*>(.*?)<\/ul>/is', "$1", $content );
        $content = preg_replace( '/<ol[^>]*>(.*?)<\/ol>/is', "$1", $content );
        $content = preg_replace( '/<li[^>]*>(.*?)<\/li>/i', "- $1\n", $content );
        $content = wp_strip_all_tags( $content );
        $content = html_entity_decode( $content );

        $markdown .= trim( $content ) . "\n";

        return $markdown;
    }

    /**
     * Convert post to HTML
     */
    private function post_to_html( WP_Post $post ): string {
        $meta_desc = get_post_meta( $post->ID, '_blog_agent_meta_description', true );

        $html = "<!DOCTYPE html>\n<html lang=\"ja\">\n<head>\n";
        $html .= "<meta charset=\"UTF-8\">\n";
        $html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "<title>" . esc_html( $post->post_title ) . "</title>\n";

        if ( $meta_desc ) {
            $html .= "<meta name=\"description\" content=\"" . esc_attr( $meta_desc ) . "\">\n";
        }

        $html .= "<style>\n";
        $html .= "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6; }\n";
        $html .= "h1 { border-bottom: 2px solid #333; padding-bottom: 10px; }\n";
        $html .= "h2 { margin-top: 2em; color: #333; }\n";
        $html .= "h3 { color: #555; }\n";
        $html .= ".meta { color: #666; font-size: 0.9em; margin-bottom: 2em; }\n";
        $html .= "</style>\n";
        $html .= "</head>\n<body>\n";

        $html .= "<article>\n";
        $html .= "<h1>" . esc_html( $post->post_title ) . "</h1>\n";
        $html .= "<div class=\"meta\">\n";
        $html .= "<p>Date: " . esc_html( $post->post_date ) . "</p>\n";
        $html .= "</div>\n";
        $html .= "<div class=\"content\">\n";
        $html .= wp_kses_post( $post->post_content );
        $html .= "</div>\n";
        $html .= "</article>\n";

        $html .= "</body>\n</html>";

        return $html;
    }

    /**
     * Create index Markdown file
     */
    private function create_index_markdown( array $posts ): string {
        $markdown = "# Blog Agent Export\n\n";
        $markdown .= "Generated: " . current_time( 'Y-m-d H:i:s' ) . "\n\n";
        $markdown .= "Total articles: " . count( $posts ) . "\n\n";
        $markdown .= "---\n\n";
        $markdown .= "## Articles\n\n";

        foreach ( $posts as $index => $post ) {
            $safe_title = sanitize_file_name( $post->post_title );
            $file_name = sprintf( '%03d-%s.md', $index + 1, $safe_title );
            $markdown .= sprintf( "%d. [%s](%s)\n", $index + 1, $post->post_title, $file_name );
        }

        return $markdown;
    }

    /**
     * Create index HTML file
     */
    private function create_index_html( array $posts ): string {
        $html = "<!DOCTYPE html>\n<html lang=\"ja\">\n<head>\n";
        $html .= "<meta charset=\"UTF-8\">\n";
        $html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "<title>Blog Agent Export</title>\n";
        $html .= "<style>\n";
        $html .= "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }\n";
        $html .= "h1 { border-bottom: 2px solid #333; padding-bottom: 10px; }\n";
        $html .= "ul { list-style-type: none; padding: 0; }\n";
        $html .= "li { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; }\n";
        $html .= "a { color: #0066cc; text-decoration: none; }\n";
        $html .= "a:hover { text-decoration: underline; }\n";
        $html .= "</style>\n";
        $html .= "</head>\n<body>\n";

        $html .= "<h1>Blog Agent Export</h1>\n";
        $html .= "<p>Generated: " . esc_html( current_time( 'Y-m-d H:i:s' ) ) . "</p>\n";
        $html .= "<p>Total articles: " . count( $posts ) . "</p>\n";
        $html .= "<hr>\n";
        $html .= "<h2>Articles</h2>\n";
        $html .= "<ul>\n";

        foreach ( $posts as $index => $post ) {
            $safe_title = sanitize_file_name( $post->post_title );
            $file_name = sprintf( '%03d-%s.html', $index + 1, $safe_title );
            $html .= "<li><a href=\"" . esc_attr( $file_name ) . "\">" . esc_html( $post->post_title ) . "</a></li>\n";
        }

        $html .= "</ul>\n";
        $html .= "</body>\n</html>";

        return $html;
    }
}
