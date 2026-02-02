<?php
/**
 * Encryption class for Dev Notes
 *
 * @package DevNotes
 * @since 1.0.0
 * @license GPL-2.0-or-later
 *
 * This file is part of Dev Notes.
 *
 * Dev Notes is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Dev Notes is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DevNotes Encryption Class
 *
 * Uses sodium_compat for secure encryption of credentials
 */
class DevNotes_Encryption {

    /**
     * Option name for storing our encryption key
     */
    const KEY_OPTION = 'devnotes_encryption_key';

    /**
     * Get or generate the encryption key
     *
     * @return string 32-byte encryption key
     */
    private static function get_encryption_key() {
        // Try to get existing key
        $stored_key = get_option( self::KEY_OPTION );

        if ( ! empty( $stored_key ) && strlen( base64_decode( $stored_key, true ) ) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
            return base64_decode( $stored_key );
        }

        // Generate a new cryptographically secure key
        $new_key = random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES );

        // Store it (base64 encoded for safe storage in options)
        update_option( self::KEY_OPTION, base64_encode( $new_key ), false );

        return $new_key;
    }

    /**
     * Regenerate the encryption key (WARNING: will make existing credentials unreadable)
     * Only use this if the key is compromised
     *
     * @return bool
     */
    public static function regenerate_key() {
        $new_key = random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
        return update_option( self::KEY_OPTION, base64_encode( $new_key ), false );
    }

    /**
     * Check if an encryption key exists
     *
     * @return bool
     */
    public static function has_key() {
        $stored_key = get_option( self::KEY_OPTION );
        return ! empty( $stored_key );
    }

    /**
     * Encrypt a value
     *
     * @param string $plaintext The value to encrypt
     * @return string|false Base64 encoded encrypted value or false on failure
     */
    public static function encrypt( $plaintext ) {
        if ( empty( $plaintext ) ) {
            return '';
        }

        try {
            $key = self::get_encryption_key();

            // Generate a random nonce
            $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

            // Encrypt the plaintext
            $ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

            // Combine nonce and ciphertext, then base64 encode
            $encrypted = base64_encode( $nonce . $ciphertext );

            // Clear sensitive data from memory
            sodium_memzero( $key );

            return $encrypted;
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'DevNotes Encryption Error: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Decrypt a value
     *
     * @param string $encrypted Base64 encoded encrypted value
     * @return string|false Decrypted value or false on failure
     */
    public static function decrypt( $encrypted ) {
        if ( empty( $encrypted ) ) {
            return '';
        }

        try {
            $key = self::get_encryption_key();

            // Decode the base64 encoded value
            $decoded = base64_decode( $encrypted, true );
            if ( false === $decoded ) {
                return false;
            }

            // Extract nonce and ciphertext
            $nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

            if ( strlen( $nonce ) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
                return false;
            }

            // Decrypt
            $plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

            // Clear sensitive data from memory
            sodium_memzero( $key );

            return $plaintext;
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'DevNotes Decryption Error: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Check if encryption is available
     *
     * @return bool
     */
    public static function is_available() {
        return function_exists( 'sodium_crypto_secretbox' ) &&
               function_exists( 'sodium_crypto_secretbox_open' );
    }
}
