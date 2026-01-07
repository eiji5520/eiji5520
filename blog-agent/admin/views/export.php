<?php
/**
 * Export Page View
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get all projects
$projects = get_posts( [
    'post_type'      => Blog_Agent_Projects::POST_TYPE,
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

// Check for errors
$export_error = isset( $_GET['export_error'] ) ? sanitize_text_field( $_GET['export_error'] ) : '';
?>

<div class="wrap blog-agent-export">
    <h1><?php esc_html_e( 'Export Articles', 'blog-agent' ); ?></h1>

    <?php if ( $export_error === 'no_posts' ) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'No articles found matching the criteria.', 'blog-agent' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="blog-agent-card">
        <h2><?php esc_html_e( 'Export Options', 'blog-agent' ); ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="blog_agent_export" />
            <?php wp_nonce_field( 'blog_agent_export', 'blog_agent_export_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="export_format"><?php esc_html_e( 'Export Format', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="export_format" id="export_format">
                            <option value="csv"><?php esc_html_e( 'CSV (Spreadsheet)', 'blog-agent' ); ?></option>
                            <option value="markdown"><?php esc_html_e( 'Markdown (ZIP)', 'blog-agent' ); ?></option>
                            <option value="html"><?php esc_html_e( 'HTML (ZIP)', 'blog-agent' ); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'CSV exports metadata only. Markdown and HTML include full content.', 'blog-agent' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="project_id"><?php esc_html_e( 'Filter by Project', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="project_id" id="project_id">
                            <option value=""><?php esc_html_e( 'All Projects', 'blog-agent' ); ?></option>
                            <?php foreach ( $projects as $project ) : ?>
                                <option value="<?php echo esc_attr( $project->ID ); ?>">
                                    <?php echo esc_html( $project->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="post_status"><?php esc_html_e( 'Filter by Status', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="post_status" id="post_status">
                            <option value="any"><?php esc_html_e( 'All Statuses', 'blog-agent' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Published', 'blog-agent' ); ?></option>
                            <option value="draft"><?php esc_html_e( 'Draft', 'blog-agent' ); ?></option>
                            <option value="future"><?php esc_html_e( 'Scheduled', 'blog-agent' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pending Review', 'blog-agent' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Export Articles', 'blog-agent' ), 'primary', 'submit', true ); ?>
        </form>
    </div>

    <!-- Export Information -->
    <div class="blog-agent-card">
        <h2><?php esc_html_e( 'Export Information', 'blog-agent' ); ?></h2>

        <h3><?php esc_html_e( 'CSV Format', 'blog-agent' ); ?></h3>
        <p><?php esc_html_e( 'Exports a spreadsheet with the following columns:', 'blog-agent' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'ID, Title, Status, QA Status, Approval Status', 'blog-agent' ); ?></li>
            <li><?php esc_html_e( 'Project ID, Meta Description, Tags', 'blog-agent' ); ?></li>
            <li><?php esc_html_e( 'Word Count, Created Date, Modified Date, URL', 'blog-agent' ); ?></li>
        </ul>

        <h3><?php esc_html_e( 'Markdown Format', 'blog-agent' ); ?></h3>
        <p><?php esc_html_e( 'Exports a ZIP file containing:', 'blog-agent' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'Individual .md files for each article', 'blog-agent' ); ?></li>
            <li><?php esc_html_e( 'Index file with links to all articles', 'blog-agent' ); ?></li>
            <li><?php esc_html_e( 'Full article content converted to Markdown', 'blog-agent' ); ?></li>
        </ul>

        <h3><?php esc_html_e( 'HTML Format', 'blog-agent' ); ?></h3>
        <p><?php esc_html_e( 'Exports a ZIP file containing:', 'blog-agent' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'Individual .html files for each article', 'blog-agent' ); ?></li>
            <li><?php esc_html_e( 'Index file with links to all articles', 'blog-agent' ); ?></li>
            <li><?php esc_html_e( 'Styled HTML with embedded CSS', 'blog-agent' ); ?></li>
        </ul>
    </div>
</div>
