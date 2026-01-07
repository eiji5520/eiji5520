<?php
/**
 * Settings Page View
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = blog_agent()->settings;
$provider = get_option( 'blog_agent_provider', 'openai' );
$providers = Blog_Agent_API_Factory::get_providers();
$openai_models = ( new Blog_Agent_OpenAI_Client() )->get_available_models();
$gemini_models = ( new Blog_Agent_Gemini_Client() )->get_available_models();

$autopost_enabled   = get_option( 'blog_agent_autopost_enabled', false );
$autopost_mode      = get_option( 'blog_agent_autopost_mode', 'draft' );
$autopost_condition = get_option( 'blog_agent_autopost_condition', 'qa_approved' );
?>

<div class="wrap blog-agent-settings">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'blog_agent_settings' ); ?>

        <!-- AI Provider Settings -->
        <div class="blog-agent-card">
            <h2><?php esc_html_e( 'AI Provider Settings', 'blog-agent' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="blog_agent_provider"><?php esc_html_e( 'Provider', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="blog_agent_provider" id="blog_agent_provider">
                            <?php foreach ( $providers as $key => $data ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $provider, $key ); ?>>
                                    <?php echo esc_html( $data['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr class="openai-settings" <?php echo $provider !== 'openai' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="blog_agent_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               name="blog_agent_openai_api_key"
                               id="blog_agent_openai_api_key"
                               value="<?php echo esc_attr( $settings->get_masked_api_key( 'blog_agent_openai_api_key' ) ); ?>"
                               class="regular-text"
                               autocomplete="off" />
                        <p class="description"><?php esc_html_e( 'Enter your OpenAI API key. It will be encrypted before storage.', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr class="gemini-settings" <?php echo $provider !== 'gemini' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="blog_agent_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               name="blog_agent_gemini_api_key"
                               id="blog_agent_gemini_api_key"
                               value="<?php echo esc_attr( $settings->get_masked_api_key( 'blog_agent_gemini_api_key' ) ); ?>"
                               class="regular-text"
                               autocomplete="off" />
                        <p class="description"><?php esc_html_e( 'Enter your Google Gemini API key. It will be encrypted before storage.', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr class="openai-settings" <?php echo $provider !== 'openai' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="blog_agent_model_openai"><?php esc_html_e( 'Model', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="blog_agent_model" id="blog_agent_model_openai" class="model-select openai-model">
                            <?php foreach ( $openai_models as $model_id => $model_name ) : ?>
                                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( get_option( 'blog_agent_model' ), $model_id ); ?>>
                                    <?php echo esc_html( $model_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr class="gemini-settings" <?php echo $provider !== 'gemini' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="blog_agent_model_gemini"><?php esc_html_e( 'Model', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="blog_agent_model" id="blog_agent_model_gemini" class="model-select gemini-model">
                            <?php foreach ( $gemini_models as $model_id => $model_name ) : ?>
                                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( get_option( 'blog_agent_model' ), $model_id ); ?>>
                                    <?php echo esc_html( $model_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog_agent_temperature"><?php esc_html_e( 'Temperature', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="blog_agent_temperature"
                               id="blog_agent_temperature"
                               value="<?php echo esc_attr( get_option( 'blog_agent_temperature', 0.7 ) ); ?>"
                               min="0"
                               max="2"
                               step="0.1"
                               class="small-text" />
                        <p class="description"><?php esc_html_e( 'Controls randomness. Lower = more focused, Higher = more creative (0-2).', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog_agent_max_tokens"><?php esc_html_e( 'Max Tokens', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="blog_agent_max_tokens"
                               id="blog_agent_max_tokens"
                               value="<?php echo esc_attr( get_option( 'blog_agent_max_tokens', 4000 ) ); ?>"
                               min="100"
                               max="128000"
                               class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Maximum number of tokens in the response.', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog_agent_timeout"><?php esc_html_e( 'Timeout (seconds)', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="blog_agent_timeout"
                               id="blog_agent_timeout"
                               value="<?php echo esc_attr( get_option( 'blog_agent_timeout', 60 ) ); ?>"
                               min="10"
                               max="300"
                               class="small-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Connection Test', 'blog-agent' ); ?></th>
                    <td>
                        <button type="button" id="test-connection" class="button button-secondary">
                            <?php esc_html_e( 'Test Connection', 'blog-agent' ); ?>
                        </button>
                        <span id="test-result" class="blog-agent-test-result"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Auto-Post Settings -->
        <div class="blog-agent-card">
            <h2><?php esc_html_e( 'Auto-Post Settings', 'blog-agent' ); ?></h2>

            <div class="blog-agent-warning">
                <strong><?php esc_html_e( '⚠️ Safety Notice:', 'blog-agent' ); ?></strong>
                <?php esc_html_e( 'Auto-posting is disabled by default. Enable only after configuring proper limits and conditions.', 'blog-agent' ); ?>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Auto-Post', 'blog-agent' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="blog_agent_autopost_enabled"
                                   value="1"
                                   <?php checked( $autopost_enabled ); ?> />
                            <?php esc_html_e( 'Enable automatic posting', 'blog-agent' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'When enabled, articles that pass QA will be automatically published or scheduled based on the settings below.', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog_agent_autopost_mode"><?php esc_html_e( 'Auto-Post Mode', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="blog_agent_autopost_mode" id="blog_agent_autopost_mode">
                            <option value="draft" <?php selected( $autopost_mode, 'draft' ); ?>>
                                <?php esc_html_e( 'Draft Only (no auto-publish)', 'blog-agent' ); ?>
                            </option>
                            <option value="schedule" <?php selected( $autopost_mode, 'schedule' ); ?>>
                                <?php esc_html_e( 'Schedule (future publish)', 'blog-agent' ); ?>
                            </option>
                            <option value="publish" <?php selected( $autopost_mode, 'publish' ); ?>>
                                <?php esc_html_e( 'Publish Immediately', 'blog-agent' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog_agent_autopost_condition"><?php esc_html_e( 'Auto-Post Condition', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="blog_agent_autopost_condition" id="blog_agent_autopost_condition">
                            <option value="qa_only" <?php selected( $autopost_condition, 'qa_only' ); ?>>
                                <?php esc_html_e( 'QA Pass Only', 'blog-agent' ); ?>
                            </option>
                            <option value="qa_approved" <?php selected( $autopost_condition, 'qa_approved' ); ?>>
                                <?php esc_html_e( 'QA Pass + Manual Approval (Recommended)', 'blog-agent' ); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Choose whether manual approval is required before auto-posting.', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr class="schedule-settings" <?php echo $autopost_mode !== 'schedule' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="blog_agent_schedule_delay"><?php esc_html_e( 'Schedule Delay (minutes)', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="blog_agent_schedule_delay"
                               id="blog_agent_schedule_delay"
                               value="<?php echo esc_attr( get_option( 'blog_agent_schedule_delay', 60 ) ); ?>"
                               min="1"
                               max="10080"
                               class="small-text" />
                        <p class="description"><?php esc_html_e( 'Minutes from now to schedule the post (1-10080, i.e., up to 7 days).', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog_agent_daily_limit"><?php esc_html_e( 'Daily Post Limit', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="blog_agent_daily_limit" id="blog_agent_daily_limit">
                            <option value="3" <?php selected( get_option( 'blog_agent_daily_limit', 10 ), 3 ); ?>>3</option>
                            <option value="5" <?php selected( get_option( 'blog_agent_daily_limit', 10 ), 5 ); ?>>5</option>
                            <option value="10" <?php selected( get_option( 'blog_agent_daily_limit', 10 ), 10 ); ?>>10</option>
                            <option value="20" <?php selected( get_option( 'blog_agent_daily_limit', 10 ), 20 ); ?>>20</option>
                            <option value="30" <?php selected( get_option( 'blog_agent_daily_limit', 10 ), 30 ); ?>>30</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Maximum number of posts that can be auto-published per day.', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog_agent_post_interval"><?php esc_html_e( 'Minimum Post Interval (minutes)', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <select name="blog_agent_post_interval" id="blog_agent_post_interval">
                            <option value="30" <?php selected( get_option( 'blog_agent_post_interval', 60 ), 30 ); ?>>30</option>
                            <option value="60" <?php selected( get_option( 'blog_agent_post_interval', 60 ), 60 ); ?>>60</option>
                            <option value="120" <?php selected( get_option( 'blog_agent_post_interval', 60 ), 120 ); ?>>120</option>
                            <option value="180" <?php selected( get_option( 'blog_agent_post_interval', 60 ), 180 ); ?>>180</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Minimum time between auto-posts.', 'blog-agent' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Emergency Stop', 'blog-agent' ); ?></th>
                    <td>
                        <button type="button" id="emergency-stop" class="button button-danger">
                            🛑 <?php esc_html_e( 'Emergency Stop All Auto-Posts', 'blog-agent' ); ?>
                        </button>
                        <p class="description"><?php esc_html_e( 'Immediately stops all pending auto-post jobs and disables auto-posting.', 'blog-agent' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- QA Settings -->
        <div class="blog-agent-card">
            <h2><?php esc_html_e( 'QA Settings', 'blog-agent' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="blog_agent_forbidden_words"><?php esc_html_e( 'Forbidden Words', 'blog-agent' ); ?></label>
                    </th>
                    <td>
                        <textarea name="blog_agent_forbidden_words"
                                  id="blog_agent_forbidden_words"
                                  rows="6"
                                  class="large-text code"><?php echo esc_textarea( get_option( 'blog_agent_forbidden_words', '' ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Enter one word per line. Articles containing these words will trigger a QA warning.', 'blog-agent' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
