<?php
/**
 * Settings class for Dev Notes
 *
 * @package DevNotes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DevNotes Settings Class
 *
 * Handles plugin settings and configuration
 */
class DevNotes_Settings {

    /**
     * Single instance
     *
     * @var DevNotes_Settings|null
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return DevNotes_Settings
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Settings are handled via AJAX in the admin page
    }

    /**
     * Get a setting value
     *
     * @param string $key     Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = get_option( 'devnotes_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Set a setting value
     *
     * @param string $key   Setting key
     * @param mixed  $value Setting value
     * @return bool
     */
    public static function set( $key, $value ) {
        $settings = get_option( 'devnotes_settings', array() );
        $settings[ $key ] = $value;
        return update_option( 'devnotes_settings', $settings );
    }

    /**
     * Get default settings
     *
     * @return array
     */
    public static function get_defaults() {
        return array(
            'require_password_verification' => false,
            'audit_log_retention_days'      => 90,
        );
    }
}
