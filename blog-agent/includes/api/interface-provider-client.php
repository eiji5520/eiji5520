<?php
/**
 * Provider Client Interface
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Blog_Agent_Provider_Client_Interface {

    /**
     * Generate content using the AI provider
     *
     * @param string $prompt The prompt to send
     * @param array  $options Additional options (temperature, max_tokens, etc.)
     * @return array{success: bool, content: string, error: string, usage: array}
     */
    public function generate( string $prompt, array $options = [] ): array;

    /**
     * Test the API connection
     *
     * @return array{success: bool, message: string}
     */
    public function test_connection(): array;

    /**
     * Get available models for this provider
     *
     * @return array
     */
    public function get_available_models(): array;

    /**
     * Get the provider name
     *
     * @return string
     */
    public function get_provider_name(): string;
}
