<?php
/**
 * Plugin Name: Briefnote
 * Plugin URI: https://github.com/SaudBarudanovic/briefnote
 * Description: A live-rendering Markdown editor and secure credentials storage for developer documentation in the WordPress admin.
 * Version: 1.0.0
 * Author: Saud Barudanovic
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: briefnote
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'BRIEFNOTE_VERSION', '1.0.0' );
define( 'BRIEFNOTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRIEFNOTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BRIEFNOTE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Custom capability constants
define( 'BRIEFNOTE_ACCESS_CAP', 'access_briefnote' );
define( 'BRIEFNOTE_VIEW_NOTES_CAP', 'view_briefnote_notes' );
define( 'BRIEFNOTE_EDIT_NOTES_CAP', 'edit_briefnote_notes' );
define( 'BRIEFNOTE_CREDENTIALS_CAP', 'view_briefnote_credentials' );
define( 'BRIEFNOTE_EDIT_CREDENTIALS_CAP', 'edit_briefnote_credentials' );

/**
 * Main plugin class
 */
final class Briefnote {

    /**
     * Single instance of the class
     *
     * @var Briefnote|null
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     *
     * @return Briefnote
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
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-encryption.php';
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-database.php';
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-notes.php';
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-credentials.php';
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-audit-log.php';
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-admin.php';
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-ajax.php';
        require_once BRIEFNOTE_PLUGIN_DIR . 'includes/class-briefnote-settings.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'init' ), 0 );

        // Initialize admin components - must be early enough for admin_menu hook
        if ( is_admin() ) {
            // Initialize admin and AJAX handlers immediately
            Briefnote_Admin::instance();
            Briefnote_Ajax::instance();
            Briefnote_Settings::instance();

            // Migrate capabilities for existing installs
            add_action( 'admin_init', array( $this, 'maybe_migrate_capabilities' ) );

            // Schedule audit log cleanup
            add_action( 'admin_init', array( $this, 'schedule_cleanup' ) );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        Briefnote_Database::create_tables();

        // Add custom capabilities to administrator role
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( BRIEFNOTE_ACCESS_CAP );
            $admin_role->add_cap( BRIEFNOTE_VIEW_NOTES_CAP );
            $admin_role->add_cap( BRIEFNOTE_EDIT_NOTES_CAP );
            $admin_role->add_cap( BRIEFNOTE_CREDENTIALS_CAP );
            $admin_role->add_cap( BRIEFNOTE_EDIT_CREDENTIALS_CAP );
        }

        // Set default options
        if ( false === get_option( 'briefnote_content' ) ) {
            add_option( 'briefnote_content', '' );
        }
        if ( false === get_option( 'briefnote_last_saved' ) ) {
            add_option( 'briefnote_last_saved', '' );
        }
        if ( false === get_option( 'briefnote_settings' ) ) {
            add_option( 'briefnote_settings', array(
                'require_password_verification' => false,
                'audit_log_retention_days' => 90,
            ) );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove all custom capabilities from all roles
        global $wp_roles;
        foreach ( $wp_roles->roles as $role_name => $role_info ) {
            $role = get_role( $role_name );
            if ( $role ) {
                $role->remove_cap( BRIEFNOTE_ACCESS_CAP );
                $role->remove_cap( BRIEFNOTE_VIEW_NOTES_CAP );
                $role->remove_cap( BRIEFNOTE_EDIT_NOTES_CAP );
                $role->remove_cap( BRIEFNOTE_CREDENTIALS_CAP );
                $role->remove_cap( BRIEFNOTE_EDIT_CREDENTIALS_CAP );
            }
        }

        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Text domain is automatically loaded by WordPress for plugins hosted on wordpress.org since WP 4.6.
    }

    /**
     * Migrate capabilities for existing installs
     */
    public function maybe_migrate_capabilities() {
        $version = get_option( 'briefnote_caps_version', 0 );

        if ( $version >= 3 ) {
            return;
        }

        $admin_role = get_role( 'administrator' );

        if ( $version < 2 ) {
            // v1 → v2: add notes caps and access meta-cap
            if ( $admin_role ) {
                $admin_role->add_cap( BRIEFNOTE_ACCESS_CAP );
                $admin_role->add_cap( BRIEFNOTE_VIEW_NOTES_CAP );
                $admin_role->add_cap( BRIEFNOTE_EDIT_NOTES_CAP );
            }

            // Grant access_briefnote to non-admin users who already had credentials
            $users = get_users( array( 'role__not_in' => array( 'administrator' ) ) );
            foreach ( $users as $user ) {
                $user_obj = new WP_User( $user->ID );
                if ( $user_obj->has_cap( BRIEFNOTE_CREDENTIALS_CAP ) ) {
                    $user_obj->add_cap( BRIEFNOTE_ACCESS_CAP );
                    // Existing credential users get edit access (preserves old behavior)
                    $user_obj->add_cap( BRIEFNOTE_EDIT_CREDENTIALS_CAP );
                }
            }
        }

        if ( $version < 3 ) {
            // v2 → v3: add edit credentials cap to admin role
            if ( $admin_role ) {
                $admin_role->add_cap( BRIEFNOTE_EDIT_CREDENTIALS_CAP );
            }
        }

        update_option( 'briefnote_caps_version', 3 );
    }

    /**
     * Schedule audit log cleanup and ensure tables exist
     */
    public function schedule_cleanup() {
        // Ensure database tables exist (in case activation hook didn't run)
        if ( ! Briefnote_Database::tables_exist() ) {
            Briefnote_Database::create_tables();
        }

        if ( ! wp_next_scheduled( 'briefnote_cleanup_audit_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'briefnote_cleanup_audit_logs' );
        }
        add_action( 'briefnote_cleanup_audit_logs', array( 'Briefnote_Audit_Log', 'cleanup_old_logs' ) );
    }
}

/**
 * Returns the main instance of Briefnote
 *
 * @return Briefnote
 */
function briefnote() {
    return Briefnote::instance();
}

// Initialize the plugin
briefnote();
