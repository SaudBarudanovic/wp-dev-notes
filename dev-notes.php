<?php
/**
 * Plugin Name: Dev Notes
 * Plugin URI: https://github.com/SaudBarudanovic/wp-dev-notes
 * Description: A live-rendering Markdown editor and secure credentials storage for developer documentation in the WordPress admin.
 * Version: 1.0.0
 * Author: Saud Barudanovic
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dev-notes
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'DEVNOTES_VERSION', '1.0.0' );
define( 'DEVNOTES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEVNOTES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DEVNOTES_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Custom capability constant
define( 'DEVNOTES_CREDENTIALS_CAP', 'view_devnotes_credentials' );

/**
 * Main plugin class
 */
final class DevNotes {

    /**
     * Single instance of the class
     *
     * @var DevNotes|null
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     *
     * @return DevNotes
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
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-encryption.php';
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-database.php';
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-notes.php';
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-credentials.php';
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-audit-log.php';
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-admin.php';
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-ajax.php';
        require_once DEVNOTES_PLUGIN_DIR . 'includes/class-devnotes-settings.php';
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
            DevNotes_Admin::instance();
            DevNotes_Ajax::instance();
            DevNotes_Settings::instance();

            // Schedule audit log cleanup
            add_action( 'admin_init', array( $this, 'schedule_cleanup' ) );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        DevNotes_Database::create_tables();

        // Add custom capability to administrator role
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( DEVNOTES_CREDENTIALS_CAP );
        }

        // Set default options
        if ( false === get_option( 'devnotes_content' ) ) {
            add_option( 'devnotes_content', '' );
        }
        if ( false === get_option( 'devnotes_last_saved' ) ) {
            add_option( 'devnotes_last_saved', '' );
        }
        if ( false === get_option( 'devnotes_settings' ) ) {
            add_option( 'devnotes_settings', array(
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
        // Remove capability from all roles
        global $wp_roles;
        foreach ( $wp_roles->roles as $role_name => $role_info ) {
            $role = get_role( $role_name );
            if ( $role ) {
                $role->remove_cap( DEVNOTES_CREDENTIALS_CAP );
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
     * Schedule audit log cleanup and ensure tables exist
     */
    public function schedule_cleanup() {
        // Ensure database tables exist (in case activation hook didn't run)
        if ( ! DevNotes_Database::tables_exist() ) {
            DevNotes_Database::create_tables();
        }

        if ( ! wp_next_scheduled( 'devnotes_cleanup_audit_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'devnotes_cleanup_audit_logs' );
        }
        add_action( 'devnotes_cleanup_audit_logs', array( 'DevNotes_Audit_Log', 'cleanup_old_logs' ) );
    }
}

/**
 * Returns the main instance of DevNotes
 *
 * @return DevNotes
 */
function devnotes() {
    return DevNotes::instance();
}

// Initialize the plugin
devnotes();
