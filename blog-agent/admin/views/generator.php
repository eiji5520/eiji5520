<?php
/**
 * Generator Page View
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$project_id = absint( $_GET['project_id'] ?? 0 );

// Get all projects
$projects = get_posts( [
    'post_type'      => Blog_Agent_Projects::POST_TYPE,
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
] );

$selected_project = null;
$project_data     = null;

if ( $project_id ) {
    $selected_project = get_post( $project_id );
    if ( $selected_project && $selected_project->post_type === Blog_Agent_Projects::POST_TYPE ) {
        $project_data = blog_agent()->projects->get_project_data( $project_id );
    }
}

// Get existing articles for this project
$existing_articles = [];
if ( $project_id ) {
    $existing_posts = get_posts( [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'meta_key'       => '_blog_agent_project_id',
        'meta_value'     => $project_id,
    ] );

    foreach ( $existing_posts as $post ) {
        $index = get_post_meta( $post->ID, '_blog_agent_plan_index', true );
        if ( $index !== '' ) {
            $existing_articles[ (int) $index ] = $post;
        }
    }
}

// Get queue status
$queue_status = [];
if ( $project_id ) {
    $queue_status = blog_agent()->generator->get_project_queue_status( $project_id );
}
?>

<div class="wrap blog-agent-generator">
    <h1><?php esc_html_e( 'Generate Articles', 'blog-agent' ); ?></h1>

    <!-- Project Selector -->
    <div class="blog-agent-card">
        <h2><?php esc_html_e( 'Select Project', 'blog-agent' ); ?></h2>

        <?php if ( empty( $projects ) ) : ?>
            <p>
                <?php esc_html_e( 'No projects found.', 'blog-agent' ); ?>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Blog_Agent_Projects::POST_TYPE ) ); ?>">
                    <?php esc_html_e( 'Create a project first.', 'blog-agent' ); ?>
                </a>
            </p>
        <?php else : ?>
            <form method="get" action="">
                <input type="hidden" name="page" value="blog-agent-generator" />
                <select name="project_id" id="project-selector" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e( '-- Select a project --', 'blog-agent' ); ?></option>
                    <?php foreach ( $projects as $project ) : ?>
                        <option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?>>
                            <?php echo esc_html( $project->post_title ); ?>
                            (<?php echo esc_html( get_post_meta( $project->ID, '_blog_agent_niche', true ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>

    <?php if ( $selected_project && $project_data ) : ?>

        <!-- Project Info -->
        <div class="blog-agent-card">
            <h2><?php echo esc_html( $selected_project->post_title ); ?></h2>
            <div class="project-meta">
                <span><strong><?php esc_html_e( 'Niche:', 'blog-agent' ); ?></strong> <?php echo esc_html( $project_data['niche'] ); ?></span>
                <span><strong><?php esc_html_e( 'Tone:', 'blog-agent' ); ?></strong> <?php echo esc_html( $project_data['tone'] ); ?></span>
                <span><strong><?php esc_html_e( 'Min Words:', 'blog-agent' ); ?></strong> <?php echo esc_html( $project_data['min_words'] ); ?></span>
            </div>
        </div>

        <?php if ( empty( $project_data['plan'] ) ) : ?>
            <div class="blog-agent-card">
                <div class="blog-agent-notice notice-warning">
                    <p>
                        <?php esc_html_e( 'This project has no plan yet.', 'blog-agent' ); ?>
                        <a href="<?php echo esc_url( get_edit_post_link( $project_id ) ); ?>">
                            <?php esc_html_e( 'Generate a plan first.', 'blog-agent' ); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php else : ?>

            <!-- Article List -->
            <div class="blog-agent-card">
                <h2><?php esc_html_e( 'Articles to Generate', 'blog-agent' ); ?></h2>

                <form id="generate-articles-form">
                    <input type="hidden" name="project_id" value="<?php echo esc_attr( $project_id ); ?>" />

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" id="select-all-articles" />
                                </th>
                                <th><?php esc_html_e( 'Title', 'blog-agent' ); ?></th>
                                <th><?php esc_html_e( 'Unique Angle', 'blog-agent' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'blog-agent' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'blog-agent' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $project_data['plan'] as $index => $article ) :
                                $is_generated = isset( $existing_articles[ $index ] );
                                $generated_post = $is_generated ? $existing_articles[ $index ] : null;

                                // Check queue status
                                $in_queue = false;
                                $queue_item = null;
                                foreach ( $queue_status as $q ) {
                                    $q_data = json_decode( $q->data, true );
                                    if ( isset( $q_data['index'] ) && $q_data['index'] === $index ) {
                                        $in_queue = true;
                                        $queue_item = $q;
                                        break;
                                    }
                                }
                            ?>
                            <tr data-index="<?php echo esc_attr( $index ); ?>">
                                <td class="check-column">
                                    <?php if ( ! $is_generated && ! $in_queue ) : ?>
                                        <input type="checkbox" name="indices[]" value="<?php echo esc_attr( $index ); ?>" />
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $is_generated ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $generated_post->ID ) ); ?>">
                                            <?php echo esc_html( $article['title'] ?? '' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html( $article['title'] ?? '' ); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $article['unique_angle'] ?? '-' ); ?></td>
                                <td>
                                    <?php if ( $is_generated ) : ?>
                                        <span class="status-badge status-generated">
                                            <?php esc_html_e( 'Generated', 'blog-agent' ); ?>
                                            (<?php echo esc_html( get_post_status_object( $generated_post->post_status )->label ); ?>)
                                        </span>
                                    <?php elseif ( $in_queue ) : ?>
                                        <span class="status-badge status-<?php echo esc_attr( $queue_item->status ); ?>">
                                            <?php echo esc_html( ucfirst( $queue_item->status ) ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="status-badge status-pending">
                                            <?php esc_html_e( 'Not Generated', 'blog-agent' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $is_generated ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $generated_post->ID ) ); ?>" class="button button-small">
                                            <?php esc_html_e( 'Edit', 'blog-agent' ); ?>
                                        </a>
                                    <?php elseif ( ! $in_queue ) : ?>
                                        <button type="button"
                                                class="button button-small generate-single"
                                                data-index="<?php echo esc_attr( $index ); ?>"
                                                data-project-id="<?php echo esc_attr( $project_id ); ?>">
                                            <?php esc_html_e( 'Generate Now', 'blog-agent' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="tablenav bottom">
                        <div class="alignleft actions">
                            <button type="button" id="queue-selected" class="button button-primary">
                                <?php esc_html_e( 'Queue Selected for Generation', 'blog-agent' ); ?>
                            </button>
                            <button type="button" id="generate-selected" class="button button-secondary">
                                <?php esc_html_e( 'Generate Selected Now', 'blog-agent' ); ?>
                            </button>
                        </div>
                    </div>
                </form>

                <div id="generation-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="progress-text"></p>
                </div>
            </div>

            <!-- Queue Status -->
            <?php if ( ! empty( $queue_status ) ) : ?>
            <div class="blog-agent-card">
                <h2><?php esc_html_e( 'Queue Status', 'blog-agent' ); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Job ID', 'blog-agent' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'blog-agent' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'blog-agent' ); ?></th>
                            <th><?php esc_html_e( 'Attempts', 'blog-agent' ); ?></th>
                            <th><?php esc_html_e( 'Scheduled', 'blog-agent' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'blog-agent' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $queue_status as $job ) : ?>
                        <tr>
                            <td><?php echo esc_html( $job->id ); ?></td>
                            <td><?php echo esc_html( $job->job_type ); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr( $job->status ); ?>">
                                    <?php echo esc_html( ucfirst( $job->status ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $job->attempts ); ?></td>
                            <td><?php echo esc_html( $job->scheduled_at ?: '-' ); ?></td>
                            <td><?php echo esc_html( $job->created_at ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>
</div>
