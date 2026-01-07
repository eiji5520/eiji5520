<?php
/**
 * Encryption Trait for API Key Security
 *
 * @package Blog_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Blog_Agent_Encryption {

    /**
     * Get encryption key derived from AUTH_KEY
     */
    private function get_encryption_key(): string {
        $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'blog-agent-default-key';
        return hash( 'sha256', $salt . 'blog_agent_encryption', true );
    }

    /**
     * Encrypt a value
     */
    public function encrypt( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }

        $key = $this->get_encryption_key();
        $iv  = openssl_random_pseudo_bytes( 16 );

        $encrypted = openssl_encrypt(
            $value,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ( false === $encrypted ) {
            return '';
        }

        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt a value
     */
    public function decrypt( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }

        $key  = $this->get_encryption_key();
        $data = base64_decode( $value );

        if ( false === $data || strlen( $data ) < 17 ) {
            return '';
        }

        $iv        = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );

        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return false === $decrypted ? '' : $decrypted;
    }

    /**
     * Mask API key for display
     */
    public function mask_api_key( string $key ): string {
        if ( empty( $key ) ) {
            return '';
        }

        $length = strlen( $key );
        if ( $length <= 8 ) {
            return str_repeat( '*', $length );
        }

        return substr( $key, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $key, -4 );
    }
}
