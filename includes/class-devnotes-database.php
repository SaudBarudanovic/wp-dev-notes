<?php
/**
 * Database class for Dev Notes
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
 * DevNotes Database Class
 *
 * Handles database table creation and management
 */
class DevNotes_Database {

    /**
     * Get credentials table name
     *
     * @return string
     */
    public static function get_credentials_table() {
        global $wpdb;
        return $wpdb->prefix . 'devnotes_credentials';
    }

    /**
     * Get audit log table name
     *
     * @return string
     */
    public static function get_audit_log_table() {
        global $wpdb;
        return $wpdb->prefix . 'devnotes_audit_log';
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Credentials table
        $credentials_table = self::get_credentials_table();
        $sql_credentials = "CREATE TABLE {$credentials_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            label varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'username_password',
            username_encrypted text,
            password_encrypted text,
            api_key_encrypted text,
            ssh_key_encrypted text,
            secure_note_encrypted text,
            url varchar(500),
            notes text,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY sort_order (sort_order),
            KEY created_by (created_by)
        ) {$charset_collate};";

        // Audit log table
        $audit_log_table = self::get_audit_log_table();
        $sql_audit_log = "CREATE TABLE {$audit_log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action_type varchar(50) NOT NULL,
            credential_label varchar(255),
            credential_id bigint(20) unsigned,
            details text,
            ip_address varchar(45),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY credential_id (credential_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_credentials );
        dbDelta( $sql_audit_log );

        // Store database version
        update_option( 'devnotes_db_version', DEVNOTES_VERSION );
    }

    /**
     * Check if tables exist and are up to date
     *
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;

        $credentials_table = self::get_credentials_table();
        $audit_log_table = self::get_audit_log_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $credentials_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $credentials_table
            )
        ) === $credentials_table;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $audit_log_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $audit_log_table
            )
        ) === $audit_log_table;

        return $credentials_exists && $audit_log_exists;
    }

    /**
     * Drop all plugin tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $credentials_table = self::get_credentials_table();
        $audit_log_table = self::get_audit_log_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query( "DROP TABLE IF EXISTS {$credentials_table}" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query( "DROP TABLE IF EXISTS {$audit_log_table}" );

        delete_option( 'devnotes_db_version' );
    }
}
