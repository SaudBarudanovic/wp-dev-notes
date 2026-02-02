<?php
/**
 * AJAX handler class for Dev Notes
 *
 * @package DevNotes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DevNotes AJAX Class
 *
 * Handles all AJAX requests for the plugin
 */
class DevNotes_Ajax {

    /**
     * Single instance
     *
     * @var DevNotes_Ajax|null
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return DevNotes_Ajax
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
        // Notes actions
        add_action( 'wp_ajax_devnotes_save_notes', array( $this, 'save_notes' ) );
        add_action( 'wp_ajax_devnotes_log_notes_access', array( $this, 'log_notes_access' ) );
        add_action( 'wp_ajax_devnotes_log_notes_copy', array( $this, 'log_notes_copy' ) );
        add_action( 'wp_ajax_devnotes_log_notes_paste', array( $this, 'log_notes_paste' ) );

        // Credential actions
        add_action( 'wp_ajax_devnotes_get_credentials', array( $this, 'get_credentials' ) );
        add_action( 'wp_ajax_devnotes_get_credential', array( $this, 'get_credential' ) );
        add_action( 'wp_ajax_devnotes_save_credential', array( $this, 'save_credential' ) );
        add_action( 'wp_ajax_devnotes_delete_credential', array( $this, 'delete_credential' ) );
        add_action( 'wp_ajax_devnotes_reveal_credential', array( $this, 'reveal_credential' ) );
        add_action( 'wp_ajax_devnotes_copy_credential', array( $this, 'copy_credential' ) );
        add_action( 'wp_ajax_devnotes_reorder_credentials', array( $this, 'reorder_credentials' ) );

        // Audit log actions
        add_action( 'wp_ajax_devnotes_get_activity_log', array( $this, 'get_activity_log' ) );

        // Settings actions
        add_action( 'wp_ajax_devnotes_save_settings', array( $this, 'save_settings' ) );

        // Password verification
        add_action( 'wp_ajax_devnotes_verify_password', array( $this, 'verify_password' ) );
    }

    /**
     * Verify nonce and capability
     *
     * @param string $capability Required capability
     * @return bool
     */
    private function verify_request( $capability = 'manage_options' ) {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'devnotes_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'dev-notes' ) ) );
            return false;
        }

        // Verify capability
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'dev-notes' ) ) );
            return false;
        }

        return true;
    }

    /**
     * Check if password verification is required and valid
     *
     * @return bool True if verification is not needed or is valid
     */
    private function check_password_verification() {
        $settings = get_option( 'devnotes_settings', array() );

        // If password verification is not required, return true
        if ( empty( $settings['require_password_verification'] ) ) {
            return true;
        }

        // Check session-based verification
        $session_key = 'devnotes_verified_' . get_current_user_id();
        $verified_time = get_transient( $session_key );

        // Verification valid for 15 minutes
        if ( $verified_time && ( time() - $verified_time ) < 900 ) {
            return true;
        }

        return false;
    }

    /**
     * Save notes content
     */
    public function save_notes() {
        if ( ! $this->verify_request( 'manage_options' ) ) {
            return;
        }

        // Nonce verified in verify_request() above.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- content is Markdown; sanitized via wp_kses_post in DevNotes_Notes::save_content().
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $save_type = isset( $_POST['save_type'] ) ? sanitize_text_field( wp_unslash( $_POST['save_type'] ) ) : 'manual';

        if ( DevNotes_Notes::save_content( $content ) ) {
            // Log the save action with content length info
            $content_length = strlen( $content );
            $word_count = str_word_count( wp_strip_all_tags( $content ) );
            DevNotes_Audit_Log::log_notes(
                'notes_saved',
                sprintf( '%s save, %d characters, ~%d words', ucfirst( $save_type ), $content_length, $word_count )
            );

            wp_send_json_success( array(
                'message'   => __( 'Notes saved successfully.', 'dev-notes' ),
                'lastSaved' => DevNotes_Notes::get_last_saved_formatted(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save notes.', 'dev-notes' ) ) );
        }
    }

    /**
     * Log notes access
     */
    public function log_notes_access() {
        if ( ! $this->verify_request( 'manage_options' ) ) {
            return;
        }

        DevNotes_Audit_Log::log_notes( 'notes_accessed', 'Notes tab opened' );
        wp_send_json_success();
    }

    /**
     * Log notes copy action
     */
    public function log_notes_copy() {
        if ( ! $this->verify_request( 'manage_options' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $selection_length = isset( $_POST['selection_length'] ) ? intval( $_POST['selection_length'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $is_full_content = isset( $_POST['is_full_content'] ) && $_POST['is_full_content'] === 'true';

        $details = $is_full_content
            ? sprintf( 'Full content copied (%d characters)', $selection_length )
            : sprintf( 'Selection copied (%d characters)', $selection_length );

        DevNotes_Audit_Log::log_notes( 'notes_copied', $details );
        wp_send_json_success();
    }

    /**
     * Log notes paste action
     */
    public function log_notes_paste() {
        if ( ! $this->verify_request( 'manage_options' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paste_length = isset( $_POST['paste_length'] ) ? intval( $_POST['paste_length'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paste_type = isset( $_POST['paste_type'] ) ? sanitize_text_field( wp_unslash( $_POST['paste_type'] ) ) : 'text';

        $details = sprintf( 'Content pasted (%d characters, %s)', $paste_length, $paste_type );

        DevNotes_Audit_Log::log_notes( 'notes_pasted', $details );
        wp_send_json_success();
    }

    /**
     * Get all credentials
     */
    public function get_credentials() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        $credentials = DevNotes_Credentials::get_all();

        // Add type labels
        $types = DevNotes_Credentials::get_types();
        foreach ( $credentials as &$cred ) {
            $cred['type_label'] = isset( $types[ $cred['type'] ] ) ? $types[ $cred['type'] ] : $cred['type'];
        }

        wp_send_json_success( array( 'credentials' => $credentials ) );
    }

    /**
     * Get single credential for editing
     */
    public function get_credential() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid credential ID.', 'dev-notes' ) ) );
            return;
        }

        // Check password verification for getting decrypted data
        if ( ! $this->check_password_verification() ) {
            wp_send_json_error( array(
                'message'              => __( 'Password verification required.', 'dev-notes' ),
                'require_verification' => true,
            ) );
            return;
        }

        $credential = DevNotes_Credentials::get( $id, true );

        if ( ! $credential ) {
            wp_send_json_error( array( 'message' => __( 'Credential not found.', 'dev-notes' ) ) );
            return;
        }

        // Sanitize sensitive fields for safe output to prevent XSS
        $sensitive_fields = array( 'username', 'password', 'api_key', 'ssh_key', 'secure_note' );
        foreach ( $sensitive_fields as $field ) {
            if ( isset( $credential[ $field ] ) ) {
                $credential[ $field ] = wp_kses( $credential[ $field ], array() );
            }
        }

        // Log credential view
        DevNotes_Audit_Log::log( 'viewed', $credential['label'], $id );

        wp_send_json_success( array( 'credential' => $credential ) );
    }

    /**
     * Save credential (create or update)
     */
    public function save_credential() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        // Verify encryption is available before saving credentials
        if ( ! DevNotes_Encryption::is_available() ) {
            wp_send_json_error( array(
                'message' => __( 'Encryption is not available. Cannot save credentials securely.', 'dev-notes' ),
            ) );
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_request(). Sensitive fields are encrypted before storage; sanitization would corrupt credential values.
        $data = array(
            'label'       => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
            'type'        => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
            'url'         => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
            'notes'       => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
            'username'    => isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '',
            'password'    => isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '',
            'api_key'     => isset( $_POST['api_key'] ) ? wp_unslash( $_POST['api_key'] ) : '',
            'ssh_key'     => isset( $_POST['ssh_key'] ) ? wp_unslash( $_POST['ssh_key'] ) : '',
            'secure_note' => isset( $_POST['secure_note'] ) ? wp_unslash( $_POST['secure_note'] ) : '',
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if ( empty( $data['label'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Label is required.', 'dev-notes' ) ) );
            return;
        }

        if ( $id ) {
            // Update existing
            $result = DevNotes_Credentials::update( $id, $data );
            $message = __( 'Credential updated successfully.', 'dev-notes' );
        } else {
            // Create new
            $result = DevNotes_Credentials::create( $data );
            $message = __( 'Credential created successfully.', 'dev-notes' );
        }

        if ( $result ) {
            wp_send_json_success( array(
                'message' => $message,
                'id'      => $id ? $id : $result,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save credential.', 'dev-notes' ) ) );
        }
    }

    /**
     * Delete credential
     */
    public function delete_credential() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid credential ID.', 'dev-notes' ) ) );
            return;
        }

        if ( DevNotes_Credentials::delete( $id ) ) {
            wp_send_json_success( array( 'message' => __( 'Credential deleted successfully.', 'dev-notes' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete credential.', 'dev-notes' ) ) );
        }
    }

    /**
     * Reveal credential value
     */
    public function reveal_credential() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        // Check password verification
        if ( ! $this->check_password_verification() ) {
            wp_send_json_error( array(
                'message'              => __( 'Password verification required.', 'dev-notes' ),
                'require_verification' => true,
            ) );
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $field = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';

        if ( ! $id || ! $field ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'dev-notes' ) ) );
            return;
        }

        $credential = DevNotes_Credentials::get( $id, true );

        if ( ! $credential ) {
            wp_send_json_error( array( 'message' => __( 'Credential not found.', 'dev-notes' ) ) );
            return;
        }

        // Log the view action
        DevNotes_Audit_Log::log( 'viewed', $credential['label'], $id, 'Field: ' . $field );

        // Return the decrypted value (sanitized for safe output)
        $value = isset( $credential[ $field ] ) ? $credential[ $field ] : '';

        // Sanitize output to prevent XSS - credentials should be plain text
        $value = wp_kses( $value, array() );

        wp_send_json_success( array( 'value' => $value ) );
    }

    /**
     * Copy credential value (for logging purposes)
     */
    public function copy_credential() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        // Check password verification
        if ( ! $this->check_password_verification() ) {
            wp_send_json_error( array(
                'message'              => __( 'Password verification required.', 'dev-notes' ),
                'require_verification' => true,
            ) );
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $field = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';

        if ( ! $id || ! $field ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'dev-notes' ) ) );
            return;
        }

        $credential = DevNotes_Credentials::get( $id, true );

        if ( ! $credential ) {
            wp_send_json_error( array( 'message' => __( 'Credential not found.', 'dev-notes' ) ) );
            return;
        }

        // Log the copy action
        DevNotes_Audit_Log::log( 'copied', $credential['label'], $id, 'Field: ' . $field );

        // Return the decrypted value for copying (sanitized for safe output)
        $value = isset( $credential[ $field ] ) ? $credential[ $field ] : '';

        // Sanitize output to prevent XSS - credentials should be plain text
        $value = wp_kses( $value, array() );

        wp_send_json_success( array( 'value' => $value ) );
    }

    /**
     * Reorder credentials
     */
    public function reorder_credentials() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $order = isset( $_POST['order'] ) ? array_map( 'intval', wp_unslash( $_POST['order'] ) ) : array();

        if ( empty( $order ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'dev-notes' ) ) );
            return;
        }

        if ( DevNotes_Credentials::update_sort_order( $order ) ) {
            wp_send_json_success( array( 'message' => __( 'Order saved.', 'dev-notes' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save order.', 'dev-notes' ) ) );
        }
    }

    /**
     * Get activity log
     */
    public function get_activity_log() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $args = array(
            'page'        => isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1,
            'per_page'    => 50,
            'action_type' => isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '',
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $logs = DevNotes_Audit_Log::get_logs( $args );

        // Format dates
        $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        foreach ( $logs['items'] as &$log ) {
            $log['created_at_formatted'] = date_i18n( $date_format, strtotime( $log['created_at'] ) );
            $log['action_label'] = DevNotes_Audit_Log::get_action_types()[ $log['action_type'] ] ?? $log['action_type'];
        }

        wp_send_json_success( $logs );
    }

    /**
     * Save settings
     */
    public function save_settings() {
        if ( ! $this->verify_request( 'manage_options' ) ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $settings = array(
            'require_password_verification' => ! empty( $_POST['require_password_verification'] ),
            'audit_log_retention_days'      => isset( $_POST['audit_log_retention_days'] ) ? intval( $_POST['audit_log_retention_days'] ) : 90,
        );

        // Handle user access
        $user_access = isset( $_POST['user_access'] ) ? array_map( 'intval', wp_unslash( $_POST['user_access'] ) ) : array();
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Get all non-admin users
        $users = get_users( array(
            'role__not_in' => array( 'administrator' ),
        ) );

        foreach ( $users as $user ) {
            $user_obj = new WP_User( $user->ID );
            if ( in_array( $user->ID, $user_access, true ) ) {
                $user_obj->add_cap( DEVNOTES_CREDENTIALS_CAP );
            } else {
                $user_obj->remove_cap( DEVNOTES_CREDENTIALS_CAP );
            }
        }

        update_option( 'devnotes_settings', $settings );

        wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'dev-notes' ) ) );
    }

    /**
     * Verify user password
     */
    public function verify_password() {
        if ( ! $this->verify_request( DEVNOTES_CREDENTIALS_CAP ) ) {
            return;
        }

        $user = wp_get_current_user();

        // Rate limiting: Check for too many failed attempts
        $attempts_key = 'devnotes_pwd_attempts_' . $user->ID;
        $lockout_key = 'devnotes_pwd_lockout_' . $user->ID;

        // Check if user is locked out
        if ( get_transient( $lockout_key ) ) {
            wp_send_json_error( array(
                'message' => __( 'Too many failed attempts. Please wait 5 minutes before trying again.', 'dev-notes' ),
            ) );
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password is passed directly to wp_check_password(); sanitization would alter the value.
        $password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

        if ( empty( $password ) ) {
            wp_send_json_error( array( 'message' => __( 'Password is required.', 'dev-notes' ) ) );
            return;
        }

        if ( ! wp_check_password( $password, $user->data->user_pass, $user->ID ) ) {
            // Increment failed attempts
            $attempts = (int) get_transient( $attempts_key );
            $attempts++;
            set_transient( $attempts_key, $attempts, 300 ); // 5 minute window

            // Lock out after 5 failed attempts
            if ( $attempts >= 5 ) {
                set_transient( $lockout_key, true, 300 ); // 5 minute lockout
                delete_transient( $attempts_key );

                // Log the lockout
                DevNotes_Audit_Log::log( 'viewed', null, null, 'Password verification lockout triggered' );

                wp_send_json_error( array(
                    'message' => __( 'Too many failed attempts. Please wait 5 minutes before trying again.', 'dev-notes' ),
                ) );
                return;
            }

            wp_send_json_error( array( 'message' => __( 'Incorrect password.', 'dev-notes' ) ) );
            return;
        }

        // Clear failed attempts on successful login
        delete_transient( $attempts_key );

        // Set session-based verification (valid for 15 minutes)
        $session_key = 'devnotes_verified_' . $user->ID;
        set_transient( $session_key, time(), 900 );

        wp_send_json_success( array( 'message' => __( 'Password verified.', 'dev-notes' ) ) );
    }
}
