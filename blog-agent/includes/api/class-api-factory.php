<?php
/**
 * API Client Factory
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blog_Agent_API_Factory {

    /**
     * Get API client based on provider setting
     */
    public static function get_client( ?string $provider = null ): Blog_Agent_Provider_Client_Interface {
        $provider = $provider ?? get_option( 'blog_agent_provider', 'openai' );

        return match ( $provider ) {
            'gemini' => new Blog_Agent_Gemini_Client(),
            default  => new Blog_Agent_OpenAI_Client(),
        };
    }

    /**
     * Get all available providers
     */
    public static function get_providers(): array {
        return [
            'openai' => [
                'name'   => 'OpenAI (ChatGPT)',
                'client' => Blog_Agent_OpenAI_Client::class,
            ],
            'gemini' => [
                'name'   => 'Google Gemini',
                'client' => Blog_Agent_Gemini_Client::class,
            ],
        ];
    }

    /**
     * Get models for a specific provider
     */
    public static function get_models_for_provider( string $provider ): array {
        $client = self::get_client( $provider );
        return $client->get_available_models();
    }
}
