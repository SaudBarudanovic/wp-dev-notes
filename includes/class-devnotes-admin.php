<?php
/**
 * Admin class for Dev Notes
 *
 * @package DevNotes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DevNotes Admin Class
 *
 * Handles admin menu, pages, and asset enqueuing
 */
class DevNotes_Admin {

    /**
     * Single instance
     *
     * @var DevNotes_Admin|null
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return DevNotes_Admin
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
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'security_notices' ) );
    }

    /**
     * Display security-related admin notices
     */
    public function security_notices() {
        // Only show to admins on the Dev Notes page
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_dev-notes' !== $screen->id ) {
            return;
        }

        // Check if sodium is available
        if ( ! DevNotes_Encryption::is_available() ) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e( 'Dev Notes Error:', 'dev-notes' ); ?></strong>
                <?php esc_html_e( 'The sodium encryption library is not available on your server. Credential storage will not work. Please contact your hosting provider to enable the sodium PHP extension (included in PHP 7.2+).', 'dev-notes' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Dev Notes', 'dev-notes' ),
            __( 'Dev Notes', 'dev-notes' ),
            'manage_options',
            'dev-notes',
            array( $this, 'render_main_page' ),
            'dashicons-editor-code',
            80
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets( $hook ) {
        // Only load on our plugin pages
        if ( 'toplevel_page_dev-notes' !== $hook ) {
            return;
        }

        // Toast UI Editor CSS
        wp_enqueue_style(
            'toastui-editor',
            DEVNOTES_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor.min.css',
            array(),
            DEVNOTES_VERSION
        );

        // Toast UI Editor Dark Theme CSS
        wp_enqueue_style(
            'toastui-editor-dark',
            DEVNOTES_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-dark.css',
            array( 'toastui-editor' ),
            DEVNOTES_VERSION
        );

        // Prism.js theme for syntax highlighting
        wp_enqueue_style(
            'prismjs',
            DEVNOTES_PLUGIN_URL . 'assets/vendor/prismjs/prism-tomorrow.min.css',
            array(),
            DEVNOTES_VERSION
        );

        // Toast UI Editor plugin for code syntax highlighting
        wp_enqueue_style(
            'toastui-editor-plugin-code-syntax-highlight',
            DEVNOTES_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-plugin-code-syntax-highlight.min.css',
            array(),
            DEVNOTES_VERSION
        );

        // Plugin styles
        wp_enqueue_style(
            'devnotes-admin',
            DEVNOTES_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DEVNOTES_VERSION
        );

        // Toast UI Editor JS
        wp_enqueue_script(
            'toastui-editor',
            DEVNOTES_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-all.min.js',
            array(),
            DEVNOTES_VERSION,
            true
        );

        // Toast UI Editor plugin for code syntax highlighting (includes Prism.js with all languages)
        wp_enqueue_script(
            'toastui-editor-plugin-code-syntax-highlight',
            DEVNOTES_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-plugin-code-syntax-highlight-all.min.js',
            array( 'toastui-editor' ),
            DEVNOTES_VERSION,
            true
        );

        // Plugin JavaScript
        wp_enqueue_script(
            'devnotes-admin',
            DEVNOTES_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'toastui-editor', 'toastui-editor-plugin-code-syntax-highlight' ),
            DEVNOTES_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'devnotes-admin',
            'devnotesAdmin',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'devnotes_nonce' ),
                'content'          => DevNotes_Notes::get_content(),
                'lastSaved'        => DevNotes_Notes::get_last_saved_formatted(),
                'canViewCredentials' => DevNotes_Credentials::current_user_can_view(),
                'credentialTypes'  => DevNotes_Credentials::get_types(),
                'strings'          => array(
                    'saving'           => __( 'Saving...', 'dev-notes' ),
                    'saved'            => __( 'Saved', 'dev-notes' ),
                    'saveError'        => __( 'Error saving', 'dev-notes' ),
                    'lastSaved'        => __( 'Last saved:', 'dev-notes' ),
                    'never'            => __( 'Never', 'dev-notes' ),
                    'confirmDelete'    => __( 'Are you sure you want to delete this credential?', 'dev-notes' ),
                    'copied'           => __( 'Copied!', 'dev-notes' ),
                    'copyFailed'       => __( 'Copy failed', 'dev-notes' ),
                    'verifyPassword'   => __( 'Please enter your WordPress password to continue.', 'dev-notes' ),
                    'passwordIncorrect' => __( 'Password incorrect. Please try again.', 'dev-notes' ),
                    'loading'          => __( 'Loading...', 'dev-notes' ),
                    'noCredentials'    => __( 'No credentials stored yet.', 'dev-notes' ),
                    'addCredential'    => __( 'Add Credential', 'dev-notes' ),
                    'editCredential'   => __( 'Edit Credential', 'dev-notes' ),
                ),
                'settings'         => get_option( 'devnotes_settings', array() ),
            )
        );
    }

    /**
     * Render main admin page
     */
    public function render_main_page() {
        $can_view_credentials = DevNotes_Credentials::current_user_can_view();
        $is_admin = current_user_can( 'manage_options' );
        ?>
        <div class="wrap devnotes-wrap">
            <h1 class="devnotes-title">
                <span class="dashicons dashicons-editor-code"></span>
                <?php esc_html_e( 'Dev Notes', 'dev-notes' ); ?>
            </h1>

            <div class="devnotes-tabs">
                <button type="button" class="devnotes-tab active" data-tab="notes">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Notes', 'dev-notes' ); ?>
                </button>
                <?php if ( $can_view_credentials ) : ?>
                <button type="button" class="devnotes-tab" data-tab="credentials">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e( 'Credentials', 'dev-notes' ); ?>
                </button>
                <button type="button" class="devnotes-tab" data-tab="activity">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Activity Log', 'dev-notes' ); ?>
                </button>
                <?php endif; ?>
                <?php if ( $is_admin ) : ?>
                <button type="button" class="devnotes-tab" data-tab="settings">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Settings', 'dev-notes' ); ?>
                </button>
                <?php endif; ?>

                <div class="devnotes-tab-actions">
                    <button type="button" id="devnotes-theme-toggle" class="devnotes-theme-toggle" title="<?php esc_attr_e( 'Toggle dark/light mode', 'dev-notes' ); ?>">
                        <span class="dashicons dashicons-lightbulb"></span>
                    </button>
                </div>
            </div>

            <!-- Notes Tab -->
            <div class="devnotes-tab-content active" id="tab-notes">
                <div class="devnotes-editor-header">
                    <div class="devnotes-save-status">
                        <span class="devnotes-last-saved">
                            <?php esc_html_e( 'Last saved:', 'dev-notes' ); ?>
                            <span id="devnotes-last-saved-time"><?php echo esc_html( DevNotes_Notes::get_last_saved_formatted() ); ?></span>
                        </span>
                        <span id="devnotes-save-indicator" class="devnotes-save-indicator"></span>
                    </div>
                    <button type="button" id="devnotes-save-btn" class="button button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save', 'dev-notes' ); ?>
                    </button>
                </div>
                <div id="devnotes-editor"></div>
            </div>

            <?php if ( $can_view_credentials ) : ?>
            <!-- Credentials Tab -->
            <div class="devnotes-tab-content" id="tab-credentials">
                <div class="devnotes-credentials-header">
                    <h2><?php esc_html_e( 'Secure Credentials', 'dev-notes' ); ?></h2>
                    <button type="button" id="devnotes-add-credential" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Add Credential', 'dev-notes' ); ?>
                    </button>
                </div>
                <div id="devnotes-credentials-list" class="devnotes-credentials-list">
                    <!-- Credentials loaded via JS -->
                </div>
            </div>

            <!-- Activity Log Tab -->
            <div class="devnotes-tab-content" id="tab-activity">
                <div class="devnotes-activity-header">
                    <h2><?php esc_html_e( 'Activity Log', 'dev-notes' ); ?></h2>
                    <div class="devnotes-activity-filters">
                        <select id="devnotes-activity-filter-action">
                            <option value=""><?php esc_html_e( 'All Actions', 'dev-notes' ); ?></option>
                            <?php foreach ( DevNotes_Audit_Log::get_action_types() as $type => $label ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="devnotes-activity-log" class="devnotes-activity-log">
                    <!-- Activity log loaded via JS -->
                </div>
                <div id="devnotes-activity-pagination" class="devnotes-pagination"></div>
            </div>
            <?php endif; ?>

            <?php if ( $is_admin ) : ?>
            <!-- Settings Tab -->
            <div class="devnotes-tab-content" id="tab-settings">
                <?php $this->render_settings_tab(); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Credential Modal -->
        <div id="devnotes-credential-modal" class="devnotes-modal">
            <div class="devnotes-modal-content">
                <div class="devnotes-modal-header">
                    <h3 id="devnotes-modal-title"><?php esc_html_e( 'Add Credential', 'dev-notes' ); ?></h3>
                    <button type="button" class="devnotes-modal-close">&times;</button>
                </div>
                <form id="devnotes-credential-form">
                    <input type="hidden" id="credential-id" name="id" value="">

                    <div class="devnotes-form-row">
                        <label for="credential-label"><?php esc_html_e( 'Label', 'dev-notes' ); ?> <span class="required">*</span></label>
                        <input type="text" id="credential-label" name="label" required>
                    </div>

                    <div class="devnotes-form-row">
                        <label for="credential-type"><?php esc_html_e( 'Type', 'dev-notes' ); ?> <span class="required">*</span></label>
                        <select id="credential-type" name="type" required>
                            <?php foreach ( DevNotes_Credentials::get_types() as $type => $label ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Username/Password fields -->
                    <div class="devnotes-form-row credential-field" data-type="username_password">
                        <label for="credential-username"><?php esc_html_e( 'Username', 'dev-notes' ); ?></label>
                        <input type="text" id="credential-username" name="username" autocomplete="off">
                    </div>
                    <div class="devnotes-form-row credential-field" data-type="username_password">
                        <label for="credential-password"><?php esc_html_e( 'Password', 'dev-notes' ); ?></label>
                        <div class="devnotes-password-field">
                            <input type="password" id="credential-password" name="password" autocomplete="off">
                            <button type="button" class="devnotes-toggle-visibility" data-target="credential-password">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- API Key field -->
                    <div class="devnotes-form-row credential-field" data-type="api_key" style="display:none;">
                        <label for="credential-api-key"><?php esc_html_e( 'API Key', 'dev-notes' ); ?></label>
                        <div class="devnotes-password-field">
                            <input type="password" id="credential-api-key" name="api_key" autocomplete="off">
                            <button type="button" class="devnotes-toggle-visibility" data-target="credential-api-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- SSH Key field -->
                    <div class="devnotes-form-row credential-field" data-type="ssh_key" style="display:none;">
                        <label for="credential-ssh-key"><?php esc_html_e( 'SSH Key / Certificate', 'dev-notes' ); ?></label>
                        <div class="devnotes-password-field">
                            <textarea id="credential-ssh-key" name="ssh_key" rows="6" autocomplete="off" class="devnotes-secure-textarea"></textarea>
                            <button type="button" class="devnotes-toggle-visibility" data-target="credential-ssh-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Secure Note field -->
                    <div class="devnotes-form-row credential-field" data-type="secure_note" style="display:none;">
                        <label for="credential-secure-note"><?php esc_html_e( 'Secure Note', 'dev-notes' ); ?></label>
                        <div class="devnotes-password-field">
                            <textarea id="credential-secure-note" name="secure_note" rows="6" autocomplete="off" class="devnotes-secure-textarea"></textarea>
                            <button type="button" class="devnotes-toggle-visibility" data-target="credential-secure-note">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <div class="devnotes-form-row">
                        <label for="credential-url"><?php esc_html_e( 'URL (optional)', 'dev-notes' ); ?></label>
                        <input type="url" id="credential-url" name="url">
                    </div>

                    <div class="devnotes-form-row">
                        <label for="credential-notes"><?php esc_html_e( 'Notes (optional, not encrypted)', 'dev-notes' ); ?></label>
                        <textarea id="credential-notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="devnotes-modal-footer">
                        <button type="button" class="button devnotes-modal-cancel"><?php esc_html_e( 'Cancel', 'dev-notes' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'dev-notes' ); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Verification Modal -->
        <div id="devnotes-password-modal" class="devnotes-modal">
            <div class="devnotes-modal-content devnotes-modal-small">
                <div class="devnotes-modal-header">
                    <h3><?php esc_html_e( 'Verify Your Identity', 'dev-notes' ); ?></h3>
                    <button type="button" class="devnotes-modal-close">&times;</button>
                </div>
                <form id="devnotes-password-form">
                    <p><?php esc_html_e( 'Please enter your WordPress password to access credentials.', 'dev-notes' ); ?></p>
                    <div class="devnotes-form-row">
                        <label for="verify-password"><?php esc_html_e( 'Password', 'dev-notes' ); ?></label>
                        <input type="password" id="verify-password" name="password" required autocomplete="current-password">
                    </div>
                    <div id="password-error" class="devnotes-error" style="display:none;"></div>
                    <div class="devnotes-modal-footer">
                        <button type="button" class="button devnotes-modal-cancel"><?php esc_html_e( 'Cancel', 'dev-notes' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Verify', 'dev-notes' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab content
     */
    private function render_settings_tab() {
        $settings = get_option( 'devnotes_settings', array() );
        ?>
        <div class="devnotes-settings">
            <h2><?php esc_html_e( 'Settings', 'dev-notes' ); ?></h2>

            <form id="devnotes-settings-form">
                <h3><?php esc_html_e( 'Security Settings', 'dev-notes' ); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Password Verification', 'dev-notes' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_password_verification" value="1"
                                    <?php checked( ! empty( $settings['require_password_verification'] ) ); ?>>
                                <?php esc_html_e( 'Require password re-entry to reveal credentials', 'dev-notes' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When enabled, users must enter their WordPress password before viewing or copying credential values.', 'dev-notes' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Audit Log Retention', 'dev-notes' ); ?></th>
                        <td>
                            <input type="number" name="audit_log_retention_days" min="0" max="365"
                                value="<?php echo esc_attr( isset( $settings['audit_log_retention_days'] ) ? $settings['audit_log_retention_days'] : 90 ); ?>">
                            <?php esc_html_e( 'days', 'dev-notes' ); ?>
                            <p class="description">
                                <?php esc_html_e( 'Automatically delete audit logs older than this many days. Set to 0 to keep logs forever.', 'dev-notes' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'User Access', 'dev-notes' ); ?></h3>

                <p class="description">
                    <?php esc_html_e( 'Grant or revoke access to the Credentials section for specific users. Only users with this permission can view, add, edit, or delete credentials.', 'dev-notes' ); ?>
                </p>

                <div id="devnotes-user-access">
                    <?php $this->render_user_access_list(); ?>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'dev-notes' ); ?></button>
                    <span id="devnotes-settings-status" class="devnotes-save-indicator"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render user access list
     */
    private function render_user_access_list() {
        // Get all users who can potentially have this capability
        $users = get_users( array(
            'role__in' => array( 'administrator', 'editor', 'author' ),
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ) );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'User', 'dev-notes' ); ?></th>
                    <th><?php esc_html_e( 'Role', 'dev-notes' ); ?></th>
                    <th><?php esc_html_e( 'Credentials Access', 'dev-notes' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $users as $user ) : ?>
                <tr>
                    <td>
                        <?php echo get_avatar( $user->ID, 32 ); ?>
                        <strong><?php echo esc_html( $user->display_name ); ?></strong>
                        <br>
                        <span class="description"><?php echo esc_html( $user->user_email ); ?></span>
                    </td>
                    <td>
                        <?php
                        $roles = array_map( 'ucfirst', $user->roles );
                        echo esc_html( implode( ', ', $roles ) );
                        ?>
                    </td>
                    <td>
                        <?php if ( in_array( 'administrator', $user->roles, true ) ) : ?>
                            <span class="devnotes-access-badge access-granted">
                                <?php esc_html_e( 'Always (Admin)', 'dev-notes' ); ?>
                            </span>
                        <?php else : ?>
                            <label class="devnotes-toggle">
                                <input type="checkbox" name="user_access[]" value="<?php echo esc_attr( $user->ID ); ?>"
                                    <?php checked( $user->has_cap( DEVNOTES_CREDENTIALS_CAP ) ); ?>>
                                <span class="devnotes-toggle-slider"></span>
                            </label>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
