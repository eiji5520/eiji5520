<?php
/**
 * Google Gemini Client Implementation
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_Gemini_Client implements Blog_Agent_Provider_Client_Interface {

    use Blog_Agent_Encryption;

    /**
     * API endpoint base
     */
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

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
        $encrypted_key = $api_key ?: get_option( 'blog_agent_gemini_api_key', '' );
        $this->api_key = $this->decrypt( $encrypted_key );
        $this->model   = $model ?: get_option( 'blog_agent_model', 'gemini-1.5-flash' );
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
                'error'   => __( 'Gemini API key is not configured.', 'blog-agent' ),
                'usage'   => [],
            ];
        }

        $temperature = $options['temperature'] ?? (float) get_option( 'blog_agent_temperature', 0.7 );
        $max_tokens  = $options['max_tokens'] ?? (int) get_option( 'blog_agent_max_tokens', 4000 );

        $endpoint = self::API_BASE . $this->model . ':generateContent?key=' . $this->api_key;

        $body = [
            'contents'         => [
                [
                    'parts' => [
                        [
                            'text' => "あなたはプロのブログライターです。SEOに最適化された、読みやすく有益な記事を作成してください。\n\n" . $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $max_tokens,
            ],
            'safetySettings'   => [
                [
                    'category'  => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
                [
                    'category'  => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
                [
                    'category'  => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
                [
                    'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_ONLY_HIGH',
                ],
            ],
        ];

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => $this->timeout,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Gemini API Error: ' . $response->get_error_message() );
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
            $this->log_error( "Gemini API Error ({$status_code}): {$error_message}" );
            return [
                'success' => false,
                'content' => '',
                'error'   => $error_message,
                'usage'   => [],
            ];
        }

        // Check for blocked content
        if ( isset( $body['candidates'][0]['finishReason'] ) && $body['candidates'][0]['finishReason'] === 'SAFETY' ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => __( 'Content was blocked by safety filters.', 'blog-agent' ),
                'usage'   => [],
            ];
        }

        $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usage   = [
            'prompt_tokens'     => $body['usageMetadata']['promptTokenCount'] ?? 0,
            'completion_tokens' => $body['usageMetadata']['candidatesTokenCount'] ?? 0,
            'total_tokens'      => $body['usageMetadata']['totalTokenCount'] ?? 0,
        ];

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

        $endpoint = self::API_BASE . $this->model . ':generateContent?key=' . $this->api_key;

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'Say "OK" if you receive this.',
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 10,
                    ],
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
            'gemini-1.5-pro'       => 'Gemini 1.5 Pro',
            'gemini-1.5-flash'     => 'Gemini 1.5 Flash',
            'gemini-1.5-flash-8b'  => 'Gemini 1.5 Flash 8B',
            'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)',
        ];
    }

    /**
     * Get provider name
     */
    public function get_provider_name(): string {
        return 'Google Gemini';
    }

    /**
     * Log error
     */
    private function log_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Blog Agent] ' . $message );
        }

        update_option( 'blog_agent_last_error', [
            'message' => $message,
            'time'    => current_time( 'mysql' ),
        ] );
    }
}
