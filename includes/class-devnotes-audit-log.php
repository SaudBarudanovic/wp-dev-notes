<?php
/**
 * Audit Log class for Dev Notes
 *
 * @package DevNotes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DevNotes Audit Log Class
 *
 * Handles logging of credential access and modifications
 */
class DevNotes_Audit_Log {

    /**
     * Action types - Credentials
     */
    const ACTION_VIEWED = 'viewed';
    const ACTION_COPIED = 'copied';
    const ACTION_CREATED = 'created';
    const ACTION_MODIFIED = 'modified';
    const ACTION_DELETED = 'deleted';

    /**
     * Action types - Notes
     */
    const ACTION_NOTES_ACCESSED = 'notes_accessed';
    const ACTION_NOTES_SAVED = 'notes_saved';
    const ACTION_NOTES_COPIED = 'notes_copied';
    const ACTION_NOTES_PASTED = 'notes_pasted';
    const ACTION_NOTES_EXPORTED = 'notes_exported';

    /**
     * Get all action types with labels
     *
     * @return array
     */
    public static function get_action_types() {
        return array(
            // Credential actions
            self::ACTION_VIEWED   => __( 'Credential Viewed', 'dev-notes' ),
            self::ACTION_COPIED   => __( 'Credential Copied', 'dev-notes' ),
            self::ACTION_CREATED  => __( 'Credential Created', 'dev-notes' ),
            self::ACTION_MODIFIED => __( 'Credential Modified', 'dev-notes' ),
            self::ACTION_DELETED  => __( 'Credential Deleted', 'dev-notes' ),
            // Notes actions
            self::ACTION_NOTES_ACCESSED => __( 'Notes Accessed', 'dev-notes' ),
            self::ACTION_NOTES_SAVED    => __( 'Notes Saved', 'dev-notes' ),
            self::ACTION_NOTES_COPIED   => __( 'Notes Copied', 'dev-notes' ),
            self::ACTION_NOTES_PASTED   => __( 'Notes Pasted', 'dev-notes' ),
            self::ACTION_NOTES_EXPORTED => __( 'Notes Exported', 'dev-notes' ),
        );
    }

    /**
     * Get credential-only action types
     *
     * @return array
     */
    public static function get_credential_action_types() {
        return array(
            self::ACTION_VIEWED   => __( 'Viewed', 'dev-notes' ),
            self::ACTION_COPIED   => __( 'Copied', 'dev-notes' ),
            self::ACTION_CREATED  => __( 'Created', 'dev-notes' ),
            self::ACTION_MODIFIED => __( 'Modified', 'dev-notes' ),
            self::ACTION_DELETED  => __( 'Deleted', 'dev-notes' ),
        );
    }

    /**
     * Get notes-only action types
     *
     * @return array
     */
    public static function get_notes_action_types() {
        return array(
            self::ACTION_NOTES_ACCESSED => __( 'Accessed', 'dev-notes' ),
            self::ACTION_NOTES_SAVED    => __( 'Saved', 'dev-notes' ),
            self::ACTION_NOTES_COPIED   => __( 'Content Copied', 'dev-notes' ),
            self::ACTION_NOTES_PASTED   => __( 'Content Pasted', 'dev-notes' ),
            self::ACTION_NOTES_EXPORTED => __( 'Exported', 'dev-notes' ),
        );
    }

    /**
     * Log a credential action
     *
     * @param string      $action_type     Type of action
     * @param string      $credential_label Label of the credential
     * @param int|null    $credential_id   ID of the credential
     * @param string|null $details         Additional details
     * @return int|false Log entry ID or false on failure
     */
    public static function log( $action_type, $credential_label, $credential_id = null, $details = null ) {
        global $wpdb;

        $table = DevNotes_Database::get_audit_log_table();

        // Validate action type
        if ( ! array_key_exists( $action_type, self::get_action_types() ) ) {
            return false;
        }

        $data = array(
            'user_id'          => get_current_user_id(),
            'action_type'      => sanitize_text_field( $action_type ),
            'credential_label' => $credential_label ? sanitize_text_field( $credential_label ) : null,
            'credential_id'    => $credential_id ? intval( $credential_id ) : null,
            'details'          => $details ? sanitize_textarea_field( $details ) : null,
            'ip_address'       => self::get_client_ip(),
            'created_at'       => current_time( 'mysql' ),
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $table, $data );

        if ( false === $result ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Log a notes action
     *
     * @param string      $action_type Type of action (notes_accessed, notes_saved, etc.)
     * @param string|null $details     Additional details (e.g., content length, selection info)
     * @return int|false Log entry ID or false on failure
     */
    public static function log_notes( $action_type, $details = null ) {
        return self::log( $action_type, null, null, $details );
    }

    /**
     * Get audit logs with pagination
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page'      => 50,
            'page'          => 1,
            'action_type'   => '',
            'user_id'       => 0,
            'credential_id' => 0,
            'date_from'     => '',
            'date_to'       => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $table = DevNotes_Database::get_audit_log_table();

        // Build query
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['action_type'] ) ) {
            $where[] = 'action_type = %s';
            $values[] = $args['action_type'];
        }

        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }

        if ( ! empty( $args['credential_id'] ) ) {
            $where[] = 'credential_id = %d';
            $values[] = $args['credential_id'];
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode( ' AND ', $where );

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total = $wpdb->get_var( $wpdb->prepare( $count_query, $values ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total = $wpdb->get_var( $count_query );
        }

        // Get results with pagination
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A );

        // Enrich with user data
        if ( $results ) {
            foreach ( $results as &$row ) {
                $user = get_userdata( $row['user_id'] );
                $row['user_display_name'] = $user ? $user->display_name : __( 'Unknown User', 'dev-notes' );
                $row['user_email'] = $user ? $user->user_email : '';
            }
        }

        return array(
            'items'       => $results ? $results : array(),
            'total'       => intval( $total ),
            'pages'       => ceil( $total / $args['per_page'] ),
            'current_page' => $args['page'],
        );
    }

    /**
     * Cleanup logs older than retention period
     */
    public static function cleanup_old_logs() {
        global $wpdb;

        $settings = get_option( 'devnotes_settings', array() );
        $retention_days = isset( $settings['audit_log_retention_days'] ) ? intval( $settings['audit_log_retention_days'] ) : 90;

        if ( $retention_days <= 0 ) {
            return; // No cleanup if retention is 0 or negative
        }

        $table = DevNotes_Database::get_audit_log_table();
        $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff_date
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip = trim( $ips[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
