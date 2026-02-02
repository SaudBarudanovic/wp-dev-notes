<?php
/**
 * Credentials class for Dev Notes
 *
 * @package DevNotes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DevNotes Credentials Class
 *
 * Handles secure credential storage and retrieval
 */
class DevNotes_Credentials {

    /**
     * Credential types
     */
    const TYPE_USERNAME_PASSWORD = 'username_password';
    const TYPE_API_KEY = 'api_key';
    const TYPE_SSH_KEY = 'ssh_key';
    const TYPE_SECURE_NOTE = 'secure_note';

    /**
     * Get all credential types
     *
     * @return array
     */
    public static function get_types() {
        return array(
            self::TYPE_USERNAME_PASSWORD => __( 'Username & Password', 'dev-notes' ),
            self::TYPE_API_KEY           => __( 'API Key', 'dev-notes' ),
            self::TYPE_SSH_KEY           => __( 'SSH Key / Certificate', 'dev-notes' ),
            self::TYPE_SECURE_NOTE       => __( 'Secure Note', 'dev-notes' ),
        );
    }

    /**
     * Get all credentials (metadata only, not decrypted)
     *
     * @return array
     */
    public static function get_all() {
        global $wpdb;

        $table = DevNotes_Database::get_credentials_table();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results(
            "SELECT id, label, type, url, notes, sort_order, created_at, updated_at, created_by
             FROM {$table}
             ORDER BY sort_order ASC, label ASC",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $results ? $results : array();
    }

    /**
     * Get a single credential by ID
     *
     * @param int  $id      Credential ID
     * @param bool $decrypt Whether to decrypt sensitive fields
     * @return array|null
     */
    public static function get( $id, $decrypt = false ) {
        global $wpdb;

        $table = DevNotes_Database::get_credentials_table();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $result ) {
            return null;
        }

        if ( $decrypt ) {
            $result = self::decrypt_credential( $result );
        }

        return $result;
    }

    /**
     * Decrypt sensitive fields in a credential
     *
     * @param array $credential The credential data
     * @return array Credential with decrypted fields
     */
    private static function decrypt_credential( $credential ) {
        $sensitive_fields = array(
            'username_encrypted',
            'password_encrypted',
            'api_key_encrypted',
            'ssh_key_encrypted',
            'secure_note_encrypted',
        );

        foreach ( $sensitive_fields as $field ) {
            if ( ! empty( $credential[ $field ] ) ) {
                $decrypted = DevNotes_Encryption::decrypt( $credential[ $field ] );
                // Store decrypted value without the _encrypted suffix
                $clean_field = str_replace( '_encrypted', '', $field );
                $credential[ $clean_field ] = $decrypted !== false ? $decrypted : '';
            }
        }

        return $credential;
    }

    /**
     * Create a new credential
     *
     * @param array $data Credential data
     * @return int|false The new credential ID or false on failure
     */
    public static function create( $data ) {
        global $wpdb;

        $table = DevNotes_Database::get_credentials_table();

        // Validate required fields
        if ( empty( $data['label'] ) || empty( $data['type'] ) ) {
            return false;
        }

        // Validate type
        if ( ! array_key_exists( $data['type'], self::get_types() ) ) {
            return false;
        }

        // Prepare insert data
        $insert_data = array(
            'label'      => sanitize_text_field( $data['label'] ),
            'type'       => sanitize_text_field( $data['type'] ),
            'url'        => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
            'notes'      => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
            'sort_order' => isset( $data['sort_order'] ) ? intval( $data['sort_order'] ) : 0,
            'created_by' => get_current_user_id(),
        );

        // Encrypt sensitive fields based on type
        switch ( $data['type'] ) {
            case self::TYPE_USERNAME_PASSWORD:
                if ( ! empty( $data['username'] ) ) {
                    $insert_data['username_encrypted'] = DevNotes_Encryption::encrypt( $data['username'] );
                }
                if ( ! empty( $data['password'] ) ) {
                    $insert_data['password_encrypted'] = DevNotes_Encryption::encrypt( $data['password'] );
                }
                break;

            case self::TYPE_API_KEY:
                if ( ! empty( $data['api_key'] ) ) {
                    $insert_data['api_key_encrypted'] = DevNotes_Encryption::encrypt( $data['api_key'] );
                }
                break;

            case self::TYPE_SSH_KEY:
                if ( ! empty( $data['ssh_key'] ) ) {
                    $insert_data['ssh_key_encrypted'] = DevNotes_Encryption::encrypt( $data['ssh_key'] );
                }
                break;

            case self::TYPE_SECURE_NOTE:
                if ( ! empty( $data['secure_note'] ) ) {
                    $insert_data['secure_note_encrypted'] = DevNotes_Encryption::encrypt( $data['secure_note'] );
                }
                break;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $table, $insert_data );

        if ( false === $result ) {
            return false;
        }

        $credential_id = $wpdb->insert_id;

        // Log creation
        DevNotes_Audit_Log::log( 'created', $insert_data['label'], $credential_id );

        return $credential_id;
    }

    /**
     * Update a credential
     *
     * @param int   $id   Credential ID
     * @param array $data Updated data
     * @return bool
     */
    public static function update( $id, $data ) {
        global $wpdb;

        $table = DevNotes_Database::get_credentials_table();

        // Get existing credential
        $existing = self::get( $id );
        if ( ! $existing ) {
            return false;
        }

        // Prepare update data
        $update_data = array(
            'updated_at' => current_time( 'mysql' ),
        );

        // Update basic fields if provided
        if ( isset( $data['label'] ) ) {
            $update_data['label'] = sanitize_text_field( $data['label'] );
        }
        if ( isset( $data['url'] ) ) {
            $update_data['url'] = esc_url_raw( $data['url'] );
        }
        if ( isset( $data['notes'] ) ) {
            $update_data['notes'] = sanitize_textarea_field( $data['notes'] );
        }
        if ( isset( $data['sort_order'] ) ) {
            $update_data['sort_order'] = intval( $data['sort_order'] );
        }

        // Handle type change
        $type = isset( $data['type'] ) ? $data['type'] : $existing['type'];
        if ( isset( $data['type'] ) && array_key_exists( $data['type'], self::get_types() ) ) {
            $update_data['type'] = sanitize_text_field( $data['type'] );

            // Clear fields not relevant to new type
            $all_encrypted_fields = array(
                'username_encrypted',
                'password_encrypted',
                'api_key_encrypted',
                'ssh_key_encrypted',
                'secure_note_encrypted',
            );
            foreach ( $all_encrypted_fields as $field ) {
                $update_data[ $field ] = null;
            }
        }

        // Encrypt sensitive fields based on type
        switch ( $type ) {
            case self::TYPE_USERNAME_PASSWORD:
                if ( isset( $data['username'] ) ) {
                    $update_data['username_encrypted'] = ! empty( $data['username'] )
                        ? DevNotes_Encryption::encrypt( $data['username'] )
                        : null;
                }
                if ( isset( $data['password'] ) ) {
                    $update_data['password_encrypted'] = ! empty( $data['password'] )
                        ? DevNotes_Encryption::encrypt( $data['password'] )
                        : null;
                }
                break;

            case self::TYPE_API_KEY:
                if ( isset( $data['api_key'] ) ) {
                    $update_data['api_key_encrypted'] = ! empty( $data['api_key'] )
                        ? DevNotes_Encryption::encrypt( $data['api_key'] )
                        : null;
                }
                break;

            case self::TYPE_SSH_KEY:
                if ( isset( $data['ssh_key'] ) ) {
                    $update_data['ssh_key_encrypted'] = ! empty( $data['ssh_key'] )
                        ? DevNotes_Encryption::encrypt( $data['ssh_key'] )
                        : null;
                }
                break;

            case self::TYPE_SECURE_NOTE:
                if ( isset( $data['secure_note'] ) ) {
                    $update_data['secure_note_encrypted'] = ! empty( $data['secure_note'] )
                        ? DevNotes_Encryption::encrypt( $data['secure_note'] )
                        : null;
                }
                break;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );

        if ( false === $result ) {
            return false;
        }

        // Log modification
        $label = isset( $update_data['label'] ) ? $update_data['label'] : $existing['label'];
        DevNotes_Audit_Log::log( 'modified', $label, $id );

        return true;
    }

    /**
     * Delete a credential
     *
     * @param int $id Credential ID
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;

        $table = DevNotes_Database::get_credentials_table();

        // Get credential for logging
        $credential = self::get( $id );
        if ( ! $credential ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false === $result ) {
            return false;
        }

        // Log deletion
        DevNotes_Audit_Log::log( 'deleted', $credential['label'], $id );

        return true;
    }

    /**
     * Update sort order for multiple credentials
     *
     * @param array $order Array of credential IDs in new order
     * @return bool
     */
    public static function update_sort_order( $order ) {
        global $wpdb;

        $table = DevNotes_Database::get_credentials_table();

        foreach ( $order as $position => $id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table,
                array( 'sort_order' => $position ),
                array( 'id' => intval( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
        }

        return true;
    }

    /**
     * Check if user can view credentials
     *
     * @return bool
     */
    public static function current_user_can_view() {
        return current_user_can( DEVNOTES_CREDENTIALS_CAP );
    }
}
