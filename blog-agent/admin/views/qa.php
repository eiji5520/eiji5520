<?php
/**
 * QA Review Page View
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get filter parameters
$filter_status   = sanitize_text_field( $_GET['qa_status'] ?? '' );
$filter_approval = sanitize_text_field( $_GET['approval'] ?? '' );

// Build query args
$args = [
    'post_type'      => 'post',
    'posts_per_page' => 20,
    'paged'          => max( 1, absint( $_GET['paged'] ?? 1 ) ),
    'meta_query'     => [
        [
            'key'     => '_blog_agent_project_id',
            'compare' => 'EXISTS',
        ],
    ],
    'orderby'        => 'date',
    'order'          => 'DESC',
];

if ( $filter_status ) {
    $args['meta_query'][] = [
        'key'   => '_blog_agent_qa_status',
        'value' => $filter_status,
    ];
}

if ( $filter_approval ) {
    $args['meta_query'][] = [
        'key'   => '_blog_agent_approval_status',
        'value' => $filter_approval,
    ];
}

$query = new WP_Query( $args );

// Get counts for filters
$count_all = get_posts( [
    'post_type'      => 'post',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        [
            'key'     => '_blog_agent_project_id',
            'compare' => 'EXISTS',
        ],
    ],
] );

$count_pending = get_posts( [
    'post_type'      => 'post',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        [
            'key'     => '_blog_agent_project_id',
            'compare' => 'EXISTS',
        ],
        [
            'key'   => '_blog_agent_qa_status',
            'value' => 'pending',
        ],
    ],
] );

$count_needs_approval = get_posts( [
    'post_type'      => 'post',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        [
            'key'     => '_blog_agent_project_id',
            'compare' => 'EXISTS',
        ],
        [
            'key'     => '_blog_agent_qa_status',
            'value'   => [ 'passed', 'warning' ],
            'compare' => 'IN',
        ],
        [
            'key'   => '_blog_agent_approval_status',
            'value' => 'draft',
        ],
    ],
] );
?>

<div class="wrap blog-agent-qa">
    <h1><?php esc_html_e( 'QA Review', 'blog-agent' ); ?></h1>

    <!-- Filters -->
    <div class="blog-agent-card">
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=blog-agent-qa' ) ); ?>"
                   class="<?php echo empty( $filter_status ) && empty( $filter_approval ) ? 'current' : ''; ?>">
                    <?php esc_html_e( 'All', 'blog-agent' ); ?>
                    <span class="count">(<?php echo count( $count_all ); ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( add_query_arg( 'qa_status', 'pending', admin_url( 'admin.php?page=blog-agent-qa' ) ) ); ?>"
                   class="<?php echo $filter_status === 'pending' ? 'current' : ''; ?>">
                    <?php esc_html_e( 'QA Pending', 'blog-agent' ); ?>
                    <span class="count">(<?php echo count( $count_pending ); ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( add_query_arg( [ 'qa_status' => 'passed', 'approval' => 'draft' ], admin_url( 'admin.php?page=blog-agent-qa' ) ) ); ?>"
                   class="<?php echo $filter_approval === 'draft' ? 'current' : ''; ?>">
                    <?php esc_html_e( 'Needs Approval', 'blog-agent' ); ?>
                    <span class="count">(<?php echo count( $count_needs_approval ); ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( add_query_arg( 'qa_status', 'failed', admin_url( 'admin.php?page=blog-agent-qa' ) ) ); ?>"
                   class="<?php echo $filter_status === 'failed' ? 'current' : ''; ?>">
                    <?php esc_html_e( 'QA Failed', 'blog-agent' ); ?>
                </a>
            </li>
        </ul>
        <div class="clear"></div>
    </div>

    <!-- Article List -->
    <div class="blog-agent-card">
        <?php if ( ! $query->have_posts() ) : ?>
            <p><?php esc_html_e( 'No articles found matching the criteria.', 'blog-agent' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-title"><?php esc_html_e( 'Title', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'QA Status', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Warnings', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Approval', 'blog-agent' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'blog-agent' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ( $query->have_posts() ) : $query->the_post();
                        $post_id       = get_the_ID();
                        $qa_status     = get_post_meta( $post_id, '_blog_agent_qa_status', true ) ?: 'pending';
                        $qa_result     = get_post_meta( $post_id, '_blog_agent_qa_result', true ) ?: [];
                        $approval      = get_post_meta( $post_id, '_blog_agent_approval_status', true ) ?: 'draft';
                        $warning_count = isset( $qa_result['warnings'] ) ? count( $qa_result['warnings'] ) : 0;
                    ?>
                    <tr data-post-id="<?php echo esc_attr( $post_id ); ?>">
                        <td class="column-title">
                            <strong>
                                <a href="<?php echo esc_url( get_edit_post_link() ); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url( get_edit_post_link() ); ?>">
                                        <?php esc_html_e( 'Edit', 'blog-agent' ); ?>
                                    </a> |
                                </span>
                                <span class="view">
                                    <a href="<?php echo esc_url( get_preview_post_link() ); ?>" target="_blank">
                                        <?php esc_html_e( 'Preview', 'blog-agent' ); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="post-status status-<?php echo esc_attr( get_post_status() ); ?>">
                                <?php echo esc_html( get_post_status_object( get_post_status() )->label ); ?>
                            </span>
                        </td>
                        <td>
                            <span class="qa-status qa-<?php echo esc_attr( $qa_status ); ?>">
                                <?php echo esc_html( blog_agent()->qa->get_status_label( $qa_status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( $warning_count > 0 ) : ?>
                                <span class="warning-count" title="<?php echo esc_attr( $this->format_warnings( $qa_result['warnings'] ?? [] ) ); ?>">
                                    ⚠️ <?php echo esc_html( $warning_count ); ?>
                                </span>
                            <?php else : ?>
                                <span class="no-warnings">✓</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="approval-status approval-<?php echo esc_attr( $approval ); ?>">
                                <?php echo esc_html( ucfirst( $approval ) ); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button"
                                    class="button button-small run-qa-btn"
                                    data-post-id="<?php echo esc_attr( $post_id ); ?>">
                                <?php esc_html_e( 'Run QA', 'blog-agent' ); ?>
                            </button>
                            <?php if ( $approval !== 'approved' ) : ?>
                                <button type="button"
                                        class="button button-small button-primary approve-article-btn"
                                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                                    <?php esc_html_e( 'Approve', 'blog-agent' ); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php
            $total_pages = $query->max_num_pages;
            if ( $total_pages > 1 ) :
                $current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
            ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => __( '&laquo;' ),
                        'next_text' => __( '&raquo;' ),
                        'total'     => $total_pages,
                        'current'   => $current_page,
                    ] );
                    ?>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; wp_reset_postdata(); ?>
    </div>

    <!-- Bulk Actions Info -->
    <div class="blog-agent-card">
        <h3><?php esc_html_e( 'Auto-Post Conditions', 'blog-agent' ); ?></h3>
        <p><?php esc_html_e( 'Current auto-post settings:', 'blog-agent' ); ?></p>
        <ul>
            <li>
                <strong><?php esc_html_e( 'Auto-Post:', 'blog-agent' ); ?></strong>
                <?php echo get_option( 'blog_agent_autopost_enabled', false ) ? esc_html__( 'Enabled', 'blog-agent' ) : esc_html__( 'Disabled', 'blog-agent' ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'Mode:', 'blog-agent' ); ?></strong>
                <?php echo esc_html( ucfirst( get_option( 'blog_agent_autopost_mode', 'draft' ) ) ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'Condition:', 'blog-agent' ); ?></strong>
                <?php
                $condition = get_option( 'blog_agent_autopost_condition', 'qa_approved' );
                echo $condition === 'qa_approved'
                    ? esc_html__( 'QA Pass + Manual Approval', 'blog-agent' )
                    : esc_html__( 'QA Pass Only', 'blog-agent' );
                ?>
            </li>
        </ul>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=blog-agent-settings' ) ); ?>">
                <?php esc_html_e( 'Change settings →', 'blog-agent' ); ?>
            </a>
        </p>
    </div>
</div>

<?php
/**
 * Format warnings for tooltip
 */
function format_warnings( array $warnings ): string {
    $lines = [];
    foreach ( $warnings as $warning ) {
        $lines[] = $warning['message'] ?? '';
    }
    return implode( "\n", $lines );
}
