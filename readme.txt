=== Briefnote ===
Contributors: saudbarudanovic
Tags: markdown, notes, credentials, developer, encryption
Requires at least: 5.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A live-rendering Markdown editor and secure credentials storage for developer documentation in the WordPress admin.

== Description ==

Briefnote is designed for developers and site administrators who need a secure, centralized location to store development notes and sensitive credentials directly within the WordPress admin interface. The plugin combines a powerful Markdown editor with enterprise-grade encryption for credential storage.

= Markdown Notes Editor =

* Live-rendering WYSIWYG editor powered by Toast UI Editor
* Syntax highlighting for PHP, JavaScript, CSS, HTML, SQL, Bash, JSON, and YAML
* GitHub Flavored Markdown support including tables, task lists, and fenced code blocks
* Auto-save with 2-second debounce to prevent data loss
* Manual save option with visual confirmation
* Dark and light mode themes

= Secure Credentials Storage =

* AES-256 equivalent encryption using libsodium (XSalsa20-Poly1305)
* Support for multiple credential types: Username/Password, API Keys, SSH Keys, Secure Notes
* Reveal and copy functionality with audit logging
* Optional password re-verification for sensitive operations
* Drag-and-drop credential reordering

= Permissions & Access Control =

* Granular per-user permissions: independent View and Edit toggles for notes and credentials
* Read-only viewer mode for users with View Notes access
* Edit permission controls who can create, modify, and delete content
* Automatic capability migration on upgrade
* Administrators always have full access

= Audit Logging =

* Comprehensive activity tracking for all credential operations
* Notes access and modification logging
* Copy/paste detection and logging
* IP address recording and user attribution
* Configurable log retention period

= Security Features =

* Automatic encryption key generation (no configuration required)
* CSRF protection on all operations
* Rate limiting on password verification
* Input sanitization and output escaping
* Memory cleanup for sensitive data

== Installation ==

1. Upload the `briefnote` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access Briefnote from the new menu item in the WordPress admin sidebar

The plugin automatically creates the required database tables and generates a secure encryption key on first use.

== Frequently Asked Questions ==

= What encryption does this plugin use? =

Briefnote uses libsodium's crypto_secretbox construction, which provides XSalsa20 stream cipher encryption with Poly1305 message authentication. This is equivalent to AES-256-GCM in security strength.

= Where is the encryption key stored? =

The encryption key is automatically generated using cryptographically secure random bytes and stored in the WordPress options table. It is created on first use with no configuration required.

= Can I grant access to non-admin users? =

Yes. Go to the Settings tab where you can independently toggle View Notes, Edit Notes, View Credentials, and Edit Credentials for each user. View grants read-only access, Edit grants full create/modify/delete access. Administrators always have full access.

= What happens if I delete the plugin? =

Deactivating the plugin removes all custom capabilities but keeps your data intact. To completely remove all data, delete the plugin and then manually remove the database tables (`wp_briefnote_credentials`, `wp_briefnote_audit_log`) and options (`briefnote_content`, `briefnote_last_saved`, `briefnote_settings`, `briefnote_encryption_key`, `briefnote_caps_version`).

= Is the Markdown content encrypted? =

No, the Markdown notes are stored as plain text in the WordPress options table. Only credential fields (passwords, API keys, SSH keys, secure notes) are encrypted.

== Screenshots ==

1. Markdown editor with live rendering and syntax highlighting
2. Credentials management interface
3. Credential entry expanded showing reveal and copy functionality
4. Activity log showing audit trail
5. Settings panel for access control and configuration

== Changelog ==

= 1.1.0 =
* Added granular per-user permissions with independent View and Edit toggles for notes and credentials
* Added read-only viewer mode for users with View Notes access
* Added Edit Credentials permission to control who can create, modify, and delete credentials
* Fixed dark mode toolbar icon rendering in the Markdown editor
* Added dark mode support for modals and activity log filters
* Added visual save confirmation with spinner and status icons for settings
* Improved card-level copy button to copy all credential fields formatted
* Fixed credentials not loading when Credentials is the default active tab
* Unified tab header heights across all tabs
* Added custom scrollbar styling for modals
* Capability migration system for seamless upgrades from 1.0.0

= 1.0.0 =
* Initial release
* Markdown editor with Toast UI Editor integration
* Secure credentials storage with sodium encryption
* Comprehensive audit logging
* Role-based access control
* Dark and light mode themes
* Auto-save functionality

== Upgrade Notice ==

= 1.1.0 =
Granular per-user permissions: independently control View and Edit access for notes and credentials. Dark mode improvements and bug fixes.

= 1.0.0 =
Initial release of Briefnote.

== Privacy Policy ==

Briefnote does not collect, transmit, or share any user data with external services. All data is stored locally in your WordPress database. The plugin does not make any external API calls or load resources from external servers.

== Third-Party Libraries ==

This plugin bundles the following third-party libraries:

* Toast UI Editor (MIT License) - https://ui.toast.com/tui-editor
* Prism.js (MIT License) - https://prismjs.com/

Both libraries are GPL-compatible and are included locally within the plugin.
