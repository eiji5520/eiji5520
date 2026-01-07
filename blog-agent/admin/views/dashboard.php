<?php
/**
 * Dashboard View
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap blog-agent-dashboard">
    <h1><?php esc_html_e( 'Blog Agent Dashboard', 'blog-agent' ); ?></h1>

    <!-- Status Cards -->
    <div class="blog-agent-status-grid">
        <!-- Auto-Post Status -->
        <div class="blog-agent-card status-card <?php echo $autopost_enabled ? 'status-active' : 'status-inactive'; ?>">
            <h3><?php esc_html_e( 'Auto-Post Status', 'blog-agent' ); ?></h3>
            <div class="status-indicator">
                <?php if ( $autopost_enabled ) : ?>
                    <span class="status-dot active"></span>
                    <span class="status-text"><?php esc_html_e( 'Active', 'blog-agent' ); ?></span>
                <?php else : ?>
                    <span class="status-dot inactive"></span>
                    <span class="status-text"><?php esc_html_e( 'Disabled', 'blog-agent' ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $autopost_enabled ) : ?>
                <button type="button" id="emergency-stop" class="button button-danger button-small">
                    🛑 <?php esc_html_e( 'Emergency Stop', 'blog-agent' ); ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- Daily Limit -->
        <div class="blog-agent-card status-card">
            <h3><?php esc_html_e( 'Today\'s Posts', 'blog-agent' ); ?></h3>
            <div class="status-number">
                <span class="current"><?php echo esc_html( $today_count ); ?></span>
                <span class="separator">/</span>
                <span class="limit"><?php echo esc_html( $daily_limit ); ?></span>
            </div>
            <div class="status-bar">
                <div class="status-bar-fill" style="width: <?php echo esc_attr( min( 100, ( $today_count / $daily_limit ) * 100 ) ); ?>%"></div>
            </div>
        </div>

        <!-- Queue Status -->
        <div class="blog-agent-card status-card">
            <h3><?php esc_html_e( 'Job Queue', 'blog-agent' ); ?></h3>
            <div class="queue-stats">
                <div class="queue-stat">
                    <span class="stat-value"><?php echo esc_html( $queue_stats->pending ?? 0 ); ?></span>
                    <span class="stat-label"><?php esc_html_e( 'Pending', 'blog-agent' ); ?></span>
                </div>
                <div class="queue-stat">
                    <span class="stat-value"><?php echo esc_html( $queue_stats->processing ?? 0 ); ?></span>
                    <span class="stat-label"><?php esc_html_e( 'Processing', 'blog-agent' ); ?></span>
                </div>
                <div class="queue-stat">
                    <span class="stat-value"><?php echo esc_html( $queue_stats->completed ?? 0 ); ?></span>
                    <span class="stat-label"><?php esc_html_e( 'Completed', 'blog-agent' ); ?></span>
                </div>
                <div class="queue-stat">
                    <span class="stat-value"><?php echo esc_html( $queue_stats->failed ?? 0 ); ?></span>
                    <span class="stat-label"><?php esc_html_e( 'Failed', 'blog-agent' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Last Error -->
        <?php if ( ! empty( $last_error ) ) : ?>
        <div class="blog-agent-card status-card status-error">
            <h3><?php esc_html_e( 'Last Error', 'blog-agent' ); ?></h3>
            <p class="error-message"><?php echo esc_html( $last_error['message'] ?? '' ); ?></p>
            <p class="error-time"><?php echo esc_html( $last_error['time'] ?? '' ); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="blog-agent-card">
        <h2><?php esc_html_e( 'Quick Actions', 'blog-agent' ); ?></h2>
        <div class="quick-actions">
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=blog_agent_project' ) ); ?>" class="button button-primary">
                <?php esc_html_e( '📋 Manage Projects', 'blog-agent' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=blog-agent-generator' ) ); ?>" class="button button-secondary">
                <?php esc_html_e( '✨ Generate Articles', 'blog-agent' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=blog-agent-qa' ) ); ?>" class="button button-secondary">
                <?php esc_html_e( '🔍 QA Review', 'blog-agent' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=blog-agent-export' ) ); ?>" class="button button-secondary">
                <?php esc_html_e( '📦 Export', 'blog-agent' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=blog-agent-settings' ) ); ?>" class="button button-secondary">
                <?php esc_html_e( '⚙️ Settings', 'blog-agent' ); ?>
            </a>
        </div>
    </div>

    <!-- Recent Articles -->
    <div class="blog-agent-card">
        <h2><?php esc_html_e( 'Recent Generated Articles', 'blog-agent' ); ?></h2>
        <?php
        $recent_posts = get_posts( [
            'post_type'      => 'post',
            'posts_per_page' => 10,
            'meta_key'       => '_blog_agent_project_id',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        if ( empty( $recent_posts ) ) :
        ?>
            <p><?php esc_html_e( 'No articles have been generated yet.', 'blog-agent' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'QA', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Approval', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'blog-agent' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_posts as $post ) :
                        $qa_status       = get_post_meta( $post->ID, '_blog_agent_qa_status', true ) ?: 'pending';
                        $approval_status = get_post_meta( $post->ID, '_blog_agent_approval_status', true ) ?: 'draft';
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
                                <?php echo esc_html( $post->post_title ); ?>
                            </a>
                        </td>
                        <td>
                            <span class="post-status status-<?php echo esc_attr( $post->post_status ); ?>">
                                <?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?>
                            </span>
                        </td>
                        <td>
                            <span class="qa-status qa-<?php echo esc_attr( $qa_status ); ?>">
                                <?php echo esc_html( ucfirst( $qa_status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <span class="approval-status approval-<?php echo esc_attr( $approval_status ); ?>">
                                <?php echo esc_html( ucfirst( $approval_status ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( get_the_date( '', $post ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'Edit', 'blog-agent' ); ?>
                            </a>
                            <?php if ( $post->post_status === 'publish' ) : ?>
                                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="button button-small" target="_blank">
                                    <?php esc_html_e( 'View', 'blog-agent' ); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
