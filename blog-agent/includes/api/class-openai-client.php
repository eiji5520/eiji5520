<?php
/**
 * OpenAI Client Implementation
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_OpenAI_Client implements Blog_Agent_Provider_Client_Interface {

    use Blog_Agent_Encryption;

    /**
     * API endpoint
     */
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * API key
     */
    private string $api_key;

    /**
     * Model name
     */
    private string $model;

    /**
     * Timeout in seconds
     */
    private int $timeout;

    /**
     * Constructor
     */
    public function __construct( string $api_key = '', string $model = '', int $timeout = 60 ) {
        $encrypted_key = $api_key ?: get_option( 'blog_agent_openai_api_key', '' );
        $this->api_key = $this->decrypt( $encrypted_key );
        $this->model   = $model ?: get_option( 'blog_agent_model', 'gpt-4o-mini' );
        $this->timeout = $timeout ?: (int) get_option( 'blog_agent_timeout', 60 );
    }

    /**
     * Generate content
     */
    public function generate( string $prompt, array $options = [] ): array {
        if ( empty( $this->api_key ) ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => __( 'OpenAI API key is not configured.', 'blog-agent' ),
                'usage'   => [],
            ];
        }

        $temperature = $options['temperature'] ?? (float) get_option( 'blog_agent_temperature', 0.7 );
        $max_tokens  = $options['max_tokens'] ?? (int) get_option( 'blog_agent_max_tokens', 4000 );

        $body = [
            'model'       => $this->model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'あなたはプロのブログライターです。SEOに最適化された、読みやすく有益な記事を作成してください。',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        ];

        $response = wp_remote_post(
            self::API_ENDPOINT,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => $this->timeout,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'OpenAI API Error: ' . $response->get_error_message() );
            return [
                'success' => false,
                'content' => '',
                'error'   => $response->get_error_message(),
                'usage'   => [],
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            $error_message = $body['error']['message'] ?? __( 'Unknown API error', 'blog-agent' );
            $this->log_error( "OpenAI API Error ({$status_code}): {$error_message}" );
            return [
                'success' => false,
                'content' => '',
                'error'   => $error_message,
                'usage'   => [],
            ];
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        $usage   = $body['usage'] ?? [];

        return [
            'success' => true,
            'content' => $content,
            'error'   => '',
            'usage'   => $usage,
        ];
    }

    /**
     * Test API connection
     */
    public function test_connection(): array {
        if ( empty( $this->api_key ) ) {
            return [
                'success' => false,
                'message' => __( 'API key is not configured.', 'blog-agent' ),
            ];
        }

        $response = wp_remote_post(
            self::API_ENDPOINT,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'model'      => $this->model,
                    'messages'   => [
                        [
                            'role'    => 'user',
                            'content' => 'Say "OK" if you receive this.',
                        ],
                    ],
                    'max_tokens' => 10,
                ] ),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code === 200 ) {
            return [
                'success' => true,
                'message' => sprintf(
                    __( 'Connection successful! Model: %s', 'blog-agent' ),
                    $this->model
                ),
            ];
        }

        $body          = json_decode( wp_remote_retrieve_body( $response ), true );
        $error_message = $body['error']['message'] ?? __( 'Unknown error', 'blog-agent' );

        return [
            'success' => false,
            'message' => $error_message,
        ];
    }

    /**
     * Get available models
     */
    public function get_available_models(): array {
        return [
            'gpt-4o'        => 'GPT-4o',
            'gpt-4o-mini'   => 'GPT-4o Mini',
            'gpt-4-turbo'   => 'GPT-4 Turbo',
            'gpt-4'         => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        ];
    }

    /**
     * Get provider name
     */
    public function get_provider_name(): string {
        return 'OpenAI';
    }

    /**
     * Log error
     */
    private function log_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Blog Agent] ' . $message );
        }

        // Store last error for admin display
        update_option( 'blog_agent_last_error', [
            'message' => $message,
            'time'    => current_time( 'mysql' ),
        ] );
    }
}
