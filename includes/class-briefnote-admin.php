<?php
/**
 * Admin class for Briefnote
 *
 * @package Briefnote
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Briefnote Admin Class
 *
 * Handles admin menu, pages, and asset enqueuing
 */
class Briefnote_Admin {

    /**
     * Single instance
     *
     * @var Briefnote_Admin|null
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return Briefnote_Admin
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
        // Only show to admins on the Briefnote page
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_briefnote' !== $screen->id ) {
            return;
        }

        // Check if sodium is available
        if ( ! Briefnote_Encryption::is_available() ) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e( 'Briefnote Error:', 'briefnote' ); ?></strong>
                <?php esc_html_e( 'The sodium encryption library is not available on your server. Credential storage will not work. Please contact your hosting provider to enable the sodium PHP extension (included in PHP 7.2+).', 'briefnote' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Briefnote', 'briefnote' ),
            __( 'Briefnote', 'briefnote' ),
            BRIEFNOTE_ACCESS_CAP,
            'briefnote',
            array( $this, 'render_main_page' ),
            'dashicons-media-document',
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
        if ( 'toplevel_page_briefnote' !== $hook ) {
            return;
        }

        // Toast UI Editor CSS
        wp_enqueue_style(
            'toastui-editor',
            BRIEFNOTE_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor.min.css',
            array(),
            BRIEFNOTE_VERSION
        );

        // Toast UI Editor Dark Theme CSS
        wp_enqueue_style(
            'toastui-editor-dark',
            BRIEFNOTE_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-dark.css',
            array( 'toastui-editor' ),
            BRIEFNOTE_VERSION
        );

        // Prism.js theme for syntax highlighting
        wp_enqueue_style(
            'prismjs',
            BRIEFNOTE_PLUGIN_URL . 'assets/vendor/prismjs/prism-tomorrow.min.css',
            array(),
            BRIEFNOTE_VERSION
        );

        // Toast UI Editor plugin for code syntax highlighting
        wp_enqueue_style(
            'toastui-editor-plugin-code-syntax-highlight',
            BRIEFNOTE_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-plugin-code-syntax-highlight.min.css',
            array(),
            BRIEFNOTE_VERSION
        );

        // Plugin styles
        wp_enqueue_style(
            'briefnote-admin',
            BRIEFNOTE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BRIEFNOTE_VERSION
        );

        // Toast UI Editor JS
        wp_enqueue_script(
            'toastui-editor',
            BRIEFNOTE_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-all.min.js',
            array(),
            BRIEFNOTE_VERSION,
            true
        );

        // Toast UI Editor plugin for code syntax highlighting (includes Prism.js with all languages)
        wp_enqueue_script(
            'toastui-editor-plugin-code-syntax-highlight',
            BRIEFNOTE_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-plugin-code-syntax-highlight-all.min.js',
            array( 'toastui-editor' ),
            BRIEFNOTE_VERSION,
            true
        );

        // Plugin JavaScript
        wp_enqueue_script(
            'briefnote-admin',
            BRIEFNOTE_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'toastui-editor', 'toastui-editor-plugin-code-syntax-highlight' ),
            BRIEFNOTE_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'briefnote-admin',
            'briefnoteAdmin',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'briefnote_nonce' ),
                'content'          => Briefnote_Notes::get_content(),
                'lastSaved'        => Briefnote_Notes::get_last_saved_formatted(),
                'canViewNotes'     => Briefnote_Notes::current_user_can_view(),
                'canEditNotes'     => Briefnote_Notes::current_user_can_edit(),
                'canViewCredentials' => Briefnote_Credentials::current_user_can_view(),
                'canEditCredentials' => Briefnote_Credentials::current_user_can_edit(),
                'isAdmin'          => current_user_can( 'manage_options' ),
                'credentialTypes'  => Briefnote_Credentials::get_types(),
                'strings'          => array(
                    'saving'           => __( 'Saving...', 'briefnote' ),
                    'saved'            => __( 'Saved', 'briefnote' ),
                    'saveError'        => __( 'Error saving', 'briefnote' ),
                    'lastSaved'        => __( 'Last saved:', 'briefnote' ),
                    'never'            => __( 'Never', 'briefnote' ),
                    'confirmDelete'    => __( 'Are you sure you want to delete this credential?', 'briefnote' ),
                    'copied'           => __( 'Copied!', 'briefnote' ),
                    'copyFailed'       => __( 'Copy failed', 'briefnote' ),
                    'verifyPassword'   => __( 'Please enter your WordPress password to continue.', 'briefnote' ),
                    'passwordIncorrect' => __( 'Password incorrect. Please try again.', 'briefnote' ),
                    'loading'          => __( 'Loading...', 'briefnote' ),
                    'noCredentials'    => __( 'No credentials stored yet.', 'briefnote' ),
                    'addCredential'    => __( 'Add Credential', 'briefnote' ),
                    'editCredential'   => __( 'Edit Credential', 'briefnote' ),
                ),
                'settings'         => get_option( 'briefnote_settings', array() ),
            )
        );
    }

    /**
     * Render main admin page
     */
    public function render_main_page() {
        $can_view_notes       = Briefnote_Notes::current_user_can_view();
        $can_edit_notes       = Briefnote_Notes::current_user_can_edit();
        $can_view_credentials = Briefnote_Credentials::current_user_can_view();
        $can_edit_credentials = Briefnote_Credentials::current_user_can_edit();
        $is_admin             = current_user_can( 'manage_options' );

        // Determine which tab is first/active
        $first_tab = null;
        if ( $can_view_notes ) {
            $first_tab = 'notes';
        } elseif ( $can_view_credentials ) {
            $first_tab = 'credentials';
        }
        ?>
        <div class="wrap briefnote-wrap">
            <h1 class="briefnote-title">
                <span class="dashicons dashicons-media-document"></span>
                <?php esc_html_e( 'Briefnote', 'briefnote' ); ?>
            </h1>

            <div class="briefnote-tabs">
                <?php if ( $can_view_notes ) : ?>
                <button type="button" class="briefnote-tab <?php echo 'notes' === $first_tab ? 'active' : ''; ?>" data-tab="notes">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Notes', 'briefnote' ); ?>
                </button>
                <?php endif; ?>
                <?php if ( $can_view_credentials ) : ?>
                <button type="button" class="briefnote-tab <?php echo 'credentials' === $first_tab ? 'active' : ''; ?>" data-tab="credentials">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e( 'Credentials', 'briefnote' ); ?>
                </button>
                <button type="button" class="briefnote-tab" data-tab="activity">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Activity Log', 'briefnote' ); ?>
                </button>
                <?php endif; ?>
                <?php if ( $is_admin ) : ?>
                <button type="button" class="briefnote-tab" data-tab="settings">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Settings', 'briefnote' ); ?>
                </button>
                <?php endif; ?>

                <div class="briefnote-tab-actions">
                    <button type="button" id="briefnote-theme-toggle" class="briefnote-theme-toggle" title="<?php esc_attr_e( 'Toggle dark/light mode', 'briefnote' ); ?>">
                        <span class="dashicons dashicons-lightbulb"></span>
                    </button>
                </div>
            </div>

            <?php if ( $can_view_notes ) : ?>
            <!-- Notes Tab -->
            <div class="briefnote-tab-content <?php echo 'notes' === $first_tab ? 'active' : ''; ?>" id="tab-notes">
                <div class="briefnote-editor-header">
                    <div class="briefnote-save-status">
                        <span class="briefnote-last-saved">
                            <?php esc_html_e( 'Last saved:', 'briefnote' ); ?>
                            <span id="briefnote-last-saved-time"><?php echo esc_html( Briefnote_Notes::get_last_saved_formatted() ); ?></span>
                        </span>
                        <span id="briefnote-save-indicator" class="briefnote-save-indicator"></span>
                    </div>
                    <?php if ( $can_edit_notes ) : ?>
                    <button type="button" id="briefnote-save-btn" class="button button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save', 'briefnote' ); ?>
                    </button>
                    <?php else : ?>
                    <span class="briefnote-readonly-badge">
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e( 'Read Only', 'briefnote' ); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div id="briefnote-editor"></div>
            </div>
            <?php endif; ?>

            <?php if ( $can_view_credentials ) : ?>
            <!-- Credentials Tab -->
            <div class="briefnote-tab-content <?php echo 'credentials' === $first_tab ? 'active' : ''; ?>" id="tab-credentials">
                <div class="briefnote-tab-header">
                    <h2><?php esc_html_e( 'Secure Credentials', 'briefnote' ); ?></h2>
                    <?php if ( $can_edit_credentials ) : ?>
                    <button type="button" id="briefnote-add-credential" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Add Credential', 'briefnote' ); ?>
                    </button>
                    <?php endif; ?>
                </div>
                <div id="briefnote-credentials-list" class="briefnote-credentials-list">
                    <!-- Credentials loaded via JS -->
                </div>
            </div>

            <!-- Activity Log Tab -->
            <div class="briefnote-tab-content" id="tab-activity">
                <div class="briefnote-tab-header">
                    <h2><?php esc_html_e( 'Activity Log', 'briefnote' ); ?></h2>
                    <div class="briefnote-activity-filters">
                        <select id="briefnote-activity-filter-action">
                            <option value=""><?php esc_html_e( 'All Actions', 'briefnote' ); ?></option>
                            <?php foreach ( Briefnote_Audit_Log::get_action_types() as $type => $label ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="briefnote-activity-log" class="briefnote-activity-log">
                    <!-- Activity log loaded via JS -->
                </div>
                <div id="briefnote-activity-pagination" class="briefnote-pagination"></div>
            </div>
            <?php endif; ?>

            <?php if ( $is_admin ) : ?>
            <!-- Settings Tab -->
            <div class="briefnote-tab-content" id="tab-settings">
                <?php $this->render_settings_tab(); ?>
            </div>
            <?php endif; ?>

        <!-- Credential Modal -->
        <div id="briefnote-credential-modal" class="briefnote-modal">
            <div class="briefnote-modal-content">
                <div class="briefnote-modal-header">
                    <h3 id="briefnote-modal-title"><?php esc_html_e( 'Add Credential', 'briefnote' ); ?></h3>
                    <button type="button" class="briefnote-modal-close">&times;</button>
                </div>
                <form id="briefnote-credential-form">
                    <input type="hidden" id="credential-id" name="id" value="">

                    <div class="briefnote-form-row">
                        <label for="credential-label"><?php esc_html_e( 'Label', 'briefnote' ); ?> <span class="required">*</span></label>
                        <input type="text" id="credential-label" name="label" required>
                    </div>

                    <div class="briefnote-form-row">
                        <label for="credential-type"><?php esc_html_e( 'Type', 'briefnote' ); ?> <span class="required">*</span></label>
                        <select id="credential-type" name="type" required>
                            <?php foreach ( Briefnote_Credentials::get_types() as $type => $label ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Username/Password fields -->
                    <div class="briefnote-form-row credential-field" data-type="username_password">
                        <label for="credential-username"><?php esc_html_e( 'Username', 'briefnote' ); ?></label>
                        <input type="text" id="credential-username" name="username" autocomplete="off">
                    </div>
                    <div class="briefnote-form-row credential-field" data-type="username_password">
                        <label for="credential-password"><?php esc_html_e( 'Password', 'briefnote' ); ?></label>
                        <div class="briefnote-password-field">
                            <input type="password" id="credential-password" name="password" autocomplete="off">
                            <button type="button" class="briefnote-toggle-visibility" data-target="credential-password">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- API Key field -->
                    <div class="briefnote-form-row credential-field" data-type="api_key" style="display:none;">
                        <label for="credential-api-key"><?php esc_html_e( 'API Key', 'briefnote' ); ?></label>
                        <div class="briefnote-password-field">
                            <input type="password" id="credential-api-key" name="api_key" autocomplete="off">
                            <button type="button" class="briefnote-toggle-visibility" data-target="credential-api-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- SSH Key field -->
                    <div class="briefnote-form-row credential-field" data-type="ssh_key" style="display:none;">
                        <label for="credential-ssh-key"><?php esc_html_e( 'SSH Key / Certificate', 'briefnote' ); ?></label>
                        <div class="briefnote-password-field">
                            <textarea id="credential-ssh-key" name="ssh_key" rows="6" autocomplete="off" class="briefnote-secure-textarea"></textarea>
                            <button type="button" class="briefnote-toggle-visibility" data-target="credential-ssh-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Secure Note field -->
                    <div class="briefnote-form-row credential-field" data-type="secure_note" style="display:none;">
                        <label for="credential-secure-note"><?php esc_html_e( 'Secure Note', 'briefnote' ); ?></label>
                        <div class="briefnote-password-field">
                            <textarea id="credential-secure-note" name="secure_note" rows="6" autocomplete="off" class="briefnote-secure-textarea"></textarea>
                            <button type="button" class="briefnote-toggle-visibility" data-target="credential-secure-note">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <div class="briefnote-form-row">
                        <label for="credential-url"><?php esc_html_e( 'URL (optional)', 'briefnote' ); ?></label>
                        <input type="url" id="credential-url" name="url">
                    </div>

                    <div class="briefnote-form-row">
                        <label for="credential-notes"><?php esc_html_e( 'Notes (optional, not encrypted)', 'briefnote' ); ?></label>
                        <textarea id="credential-notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="briefnote-modal-footer">
                        <button type="button" class="button briefnote-modal-cancel"><?php esc_html_e( 'Cancel', 'briefnote' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'briefnote' ); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Verification Modal -->
        <div id="briefnote-password-modal" class="briefnote-modal">
            <div class="briefnote-modal-content briefnote-modal-small">
                <div class="briefnote-modal-header">
                    <h3><?php esc_html_e( 'Verify Your Identity', 'briefnote' ); ?></h3>
                    <button type="button" class="briefnote-modal-close">&times;</button>
                </div>
                <form id="briefnote-password-form">
                    <p><?php esc_html_e( 'Please enter your WordPress password to access credentials.', 'briefnote' ); ?></p>
                    <div class="briefnote-form-row">
                        <label for="verify-password"><?php esc_html_e( 'Password', 'briefnote' ); ?></label>
                        <input type="password" id="verify-password" name="password" required autocomplete="current-password">
                    </div>
                    <div id="password-error" class="briefnote-error" style="display:none;"></div>
                    <div class="briefnote-modal-footer">
                        <button type="button" class="button briefnote-modal-cancel"><?php esc_html_e( 'Cancel', 'briefnote' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Verify', 'briefnote' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        </div>
        <?php
    }

    /**
     * Render settings tab content
     */
    private function render_settings_tab() {
        $settings = get_option( 'briefnote_settings', array() );
        ?>
        <div class="briefnote-settings">
            <div class="briefnote-tab-header">
                <h2><?php esc_html_e( 'Settings', 'briefnote' ); ?></h2>
            </div>

            <form id="briefnote-settings-form">
                <h3><?php esc_html_e( 'Security Settings', 'briefnote' ); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Password Verification', 'briefnote' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_password_verification" value="1"
                                    <?php checked( ! empty( $settings['require_password_verification'] ) ); ?>>
                                <?php esc_html_e( 'Require password re-entry to reveal credentials', 'briefnote' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When enabled, users must enter their WordPress password before viewing or copying credential values.', 'briefnote' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Audit Log Retention', 'briefnote' ); ?></th>
                        <td>
                            <input type="number" name="audit_log_retention_days" min="0" max="365"
                                value="<?php echo esc_attr( isset( $settings['audit_log_retention_days'] ) ? $settings['audit_log_retention_days'] : 90 ); ?>">
                            <?php esc_html_e( 'days', 'briefnote' ); ?>
                            <p class="description">
                                <?php esc_html_e( 'Automatically delete audit logs older than this many days. Set to 0 to keep logs forever.', 'briefnote' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'User Access', 'briefnote' ); ?></h3>

                <p class="description">
                    <?php esc_html_e( 'Manage granular access permissions for each user. View grants read-only access. Edit grants full create/modify/delete access. Edit implies View.', 'briefnote' ); ?>
                </p>

                <div id="briefnote-user-access">
                    <?php $this->render_user_access_list(); ?>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'briefnote' ); ?></button>
                    <span id="briefnote-settings-status" class="briefnote-save-indicator"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render user access list
     */
    private function render_user_access_list() {
        $users = get_users( array(
            'role__in' => array( 'administrator', 'editor', 'author' ),
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ) );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'User', 'briefnote' ); ?></th>
                    <th class="briefnote-role-col"><?php esc_html_e( 'Role', 'briefnote' ); ?></th>
                    <th class="briefnote-cap-col"><?php esc_html_e( 'View Notes', 'briefnote' ); ?></th>
                    <th class="briefnote-cap-col"><?php esc_html_e( 'Edit Notes', 'briefnote' ); ?></th>
                    <th class="briefnote-cap-col"><?php esc_html_e( 'View Credentials', 'briefnote' ); ?></th>
                    <th class="briefnote-cap-col"><?php esc_html_e( 'Edit Credentials', 'briefnote' ); ?></th>
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
                    <?php if ( in_array( 'administrator', $user->roles, true ) ) : ?>
                        <td class="briefnote-cap-col">
                            <span class="briefnote-access-badge access-granted"><?php esc_html_e( 'Always', 'briefnote' ); ?></span>
                        </td>
                        <td class="briefnote-cap-col">
                            <span class="briefnote-access-badge access-granted"><?php esc_html_e( 'Always', 'briefnote' ); ?></span>
                        </td>
                        <td class="briefnote-cap-col">
                            <span class="briefnote-access-badge access-granted"><?php esc_html_e( 'Always', 'briefnote' ); ?></span>
                        </td>
                        <td class="briefnote-cap-col">
                            <span class="briefnote-access-badge access-granted"><?php esc_html_e( 'Always', 'briefnote' ); ?></span>
                        </td>
                    <?php else : ?>
                        <td class="briefnote-cap-col">
                            <label class="briefnote-toggle">
                                <input type="checkbox" name="user_cap_view_notes[]"
                                    value="<?php echo esc_attr( $user->ID ); ?>"
                                    data-user="<?php echo esc_attr( $user->ID ); ?>"
                                    data-cap="view_notes"
                                    <?php checked( $user->has_cap( BRIEFNOTE_VIEW_NOTES_CAP ) ); ?>>
                                <span class="briefnote-toggle-slider"></span>
                            </label>
                        </td>
                        <td class="briefnote-cap-col">
                            <label class="briefnote-toggle">
                                <input type="checkbox" name="user_cap_edit_notes[]"
                                    value="<?php echo esc_attr( $user->ID ); ?>"
                                    data-user="<?php echo esc_attr( $user->ID ); ?>"
                                    data-cap="edit_notes"
                                    <?php checked( $user->has_cap( BRIEFNOTE_EDIT_NOTES_CAP ) ); ?>>
                                <span class="briefnote-toggle-slider"></span>
                            </label>
                        </td>
                        <td class="briefnote-cap-col">
                            <label class="briefnote-toggle">
                                <input type="checkbox" name="user_cap_credentials[]"
                                    value="<?php echo esc_attr( $user->ID ); ?>"
                                    data-user="<?php echo esc_attr( $user->ID ); ?>"
                                    data-cap="credentials"
                                    <?php checked( $user->has_cap( BRIEFNOTE_CREDENTIALS_CAP ) ); ?>>
                                <span class="briefnote-toggle-slider"></span>
                            </label>
                        </td>
                        <td class="briefnote-cap-col">
                            <label class="briefnote-toggle">
                                <input type="checkbox" name="user_cap_edit_credentials[]"
                                    value="<?php echo esc_attr( $user->ID ); ?>"
                                    data-user="<?php echo esc_attr( $user->ID ); ?>"
                                    data-cap="edit_credentials"
                                    <?php checked( $user->has_cap( BRIEFNOTE_EDIT_CREDENTIALS_CAP ) ); ?>>
                                <span class="briefnote-toggle-slider"></span>
                            </label>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
