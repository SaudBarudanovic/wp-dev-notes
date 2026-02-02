/**
 * Dev Notes Admin JavaScript
 */

(function($) {
    'use strict';

    // Editor instance
    let editor = null;
    let saveTimeout = null;
    let isDirty = false;
    let isVerified = false;
    let pendingAction = null;
    let notesAccessLogged = false;

    /**
     * Initialize the plugin
     */
    function init() {
        initTheme();
        initTabs();
        initEditor();
        initCredentials();
        initActivityLog();
        initSettings();
        initModals();
        initNotesTracking();

        // Log initial notes access (notes tab is default)
        logNotesAccess();
    }

    /**
     * Initialize theme (dark/light mode)
     */
    function initTheme() {
        // Check for saved preference or system preference
        const savedTheme = localStorage.getItem('devnotes_theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.querySelector('.devnotes-wrap').classList.add('devnotes-dark');
            document.body.classList.add('devnotes-dark-mode');
        }

        // Toggle button
        $('#devnotes-theme-toggle').on('click', function() {
            const wrap = document.querySelector('.devnotes-wrap');
            wrap.classList.toggle('devnotes-dark');
            document.body.classList.toggle('devnotes-dark-mode');

            const isDark = wrap.classList.contains('devnotes-dark');
            localStorage.setItem('devnotes_theme', isDark ? 'dark' : 'light');

            // Reinitialize editor for theme change
            if (editor) {
                const content = editor.getMarkdown();
                initEditor(content);
            }
        });
    }

    /**
     * Initialize tabs
     */
    function initTabs() {
        $('.devnotes-tab').on('click', function() {
            const tabId = $(this).data('tab');

            // Update tab buttons
            $('.devnotes-tab').removeClass('active');
            $(this).addClass('active');

            // Update tab content
            $('.devnotes-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');

            // Load data for specific tabs and log access
            if (tabId === 'notes') {
                logNotesAccess();
            } else if (tabId === 'credentials' && devnotesAdmin.canViewCredentials) {
                loadCredentials();
            } else if (tabId === 'activity' && devnotesAdmin.canViewCredentials) {
                loadActivityLog();
            }
        });
    }

    /**
     * Initialize Toast UI Editor
     */
    function initEditor(initialContent) {
        const container = document.getElementById('devnotes-editor');
        if (!container) return;

        // Clear existing editor
        container.innerHTML = '';

        const isDark = document.querySelector('.devnotes-wrap').classList.contains('devnotes-dark');
        const content = initialContent !== undefined ? initialContent : devnotesAdmin.content;

        // Initialize Toast UI Editor
        editor = new toastui.Editor({
            el: container,
            height: '600px',
            initialEditType: 'wysiwyg',
            previewStyle: 'vertical',
            initialValue: content,
            theme: isDark ? 'dark' : 'light',
            usageStatistics: false,
            plugins: [toastui.Editor.plugin.codeSyntaxHighlight],
            toolbarItems: [
                ['heading', 'bold', 'italic', 'strike'],
                ['hr', 'quote'],
                ['ul', 'ol', 'task'],
                ['table', 'link', 'image'],
                ['code', 'codeblock'],
                ['scrollSync'],
            ],
            events: {
                change: function() {
                    isDirty = true;
                    debounceSave();
                }
            }
        });

        // Manual save button
        $('#devnotes-save-btn').off('click').on('click', function() {
            saveNotes('manual');
        });

        // Initialize copy/paste tracking on the editor
        initEditorCopyPasteTracking();
    }

    /**
     * Debounce save (2 seconds after user stops typing)
     */
    function debounceSave() {
        clearTimeout(saveTimeout);
        showSaveIndicator('saving');

        saveTimeout = setTimeout(function() {
            saveNotes('auto');
        }, 2000);
    }

    /**
     * Save notes via AJAX
     */
    function saveNotes(saveType) {
        if (!editor) return;

        saveType = saveType || 'manual';
        const content = editor.getMarkdown();

        showSaveIndicator('saving');

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_save_notes',
                nonce: devnotesAdmin.nonce,
                content: content,
                save_type: saveType
            },
            success: function(response) {
                if (response.success) {
                    isDirty = false;
                    showSaveIndicator('saved');
                    $('#devnotes-last-saved-time').text(response.data.lastSaved);
                } else {
                    showSaveIndicator('error');
                }
            },
            error: function() {
                showSaveIndicator('error');
            }
        });
    }

    /**
     * Show save indicator
     */
    function showSaveIndicator(status) {
        const indicator = $('#devnotes-save-indicator');
        indicator.removeClass('saving saved error');

        switch (status) {
            case 'saving':
                indicator.addClass('saving').text(devnotesAdmin.strings.saving);
                break;
            case 'saved':
                indicator.addClass('saved').text(devnotesAdmin.strings.saved);
                setTimeout(function() {
                    indicator.removeClass('saved').text('');
                }, 3000);
                break;
            case 'error':
                indicator.addClass('error').text(devnotesAdmin.strings.saveError);
                break;
        }
    }

    /**
     * Initialize credentials functionality
     */
    function initCredentials() {
        if (!devnotesAdmin.canViewCredentials) return;

        // Add credential button
        $('#devnotes-add-credential').on('click', function() {
            openCredentialModal();
        });

        // Type change handler
        $('#credential-type').on('change', function() {
            updateCredentialFields($(this).val());
        });

        // Form submission
        $('#devnotes-credential-form').on('submit', function(e) {
            e.preventDefault();
            saveCredential();
        });

        // Toggle visibility buttons
        $(document).on('click', '.devnotes-toggle-visibility', function() {
            const targetId = $(this).data('target');
            const target = $('#' + targetId);
            const icon = $(this).find('.dashicons');

            if (target.is('input')) {
                if (target.attr('type') === 'password') {
                    target.attr('type', 'text');
                    icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    target.attr('type', 'password');
                    icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
            } else if (target.is('textarea')) {
                target.toggleClass('visible');
                icon.toggleClass('dashicons-visibility dashicons-hidden');
            }
        });
    }

    /**
     * Load credentials list
     */
    function loadCredentials() {
        const container = $('#devnotes-credentials-list');
        container.html('<div class="devnotes-loading"><div class="devnotes-spinner"></div></div>');

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_get_credentials',
                nonce: devnotesAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCredentials(response.data.credentials);
                }
            }
        });
    }

    /**
     * Render credentials list
     */
    function renderCredentials(credentials) {
        const container = $('#devnotes-credentials-list');

        if (!credentials || credentials.length === 0) {
            container.html(`
                <div class="devnotes-empty-state">
                    <span class="dashicons dashicons-lock"></span>
                    <p>${devnotesAdmin.strings.noCredentials}</p>
                </div>
            `);
            return;
        }

        let html = '';
        credentials.forEach(function(cred) {
            const typeIcon = getTypeIcon(cred.type);
            html += `
                <div class="devnotes-credential-card" data-id="${cred.id}" draggable="true">
                    <div class="devnotes-credential-info">
                        <div class="devnotes-credential-label">${escapeHtml(cred.label)}</div>
                        <div class="devnotes-credential-meta">
                            <span><span class="dashicons ${typeIcon}"></span> ${escapeHtml(cred.type_label)}</span>
                            ${cred.url ? `<span><span class="dashicons dashicons-admin-links"></span> <a href="${escapeHtml(cred.url)}" target="_blank">${truncateUrl(cred.url)}</a></span>` : ''}
                        </div>
                    </div>
                    <div class="devnotes-credential-actions">
                        <button type="button" class="reveal-btn" data-id="${cred.id}" title="Reveal">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" class="copy-btn" data-id="${cred.id}" title="Copy">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                        <button type="button" class="edit-btn" data-id="${cred.id}" title="Edit">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="delete delete-btn" data-id="${cred.id}" title="Delete">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="devnotes-credential-fields" id="fields-${cred.id}"></div>
                </div>
            `;
        });

        container.html(html);

        // Bind action buttons
        container.find('.reveal-btn').on('click', function() {
            revealCredential($(this).data('id'));
        });

        container.find('.copy-btn').on('click', function() {
            copyCredential($(this).data('id'));
        });

        container.find('.edit-btn').on('click', function() {
            editCredential($(this).data('id'));
        });

        container.find('.delete-btn').on('click', function() {
            deleteCredential($(this).data('id'));
        });

        // Initialize drag and drop
        initDragAndDrop();
    }

    /**
     * Get icon for credential type
     */
    function getTypeIcon(type) {
        const icons = {
            'username_password': 'dashicons-admin-users',
            'api_key': 'dashicons-admin-network',
            'ssh_key': 'dashicons-media-code',
            'secure_note': 'dashicons-media-text'
        };
        return icons[type] || 'dashicons-lock';
    }

    /**
     * Open credential modal for adding
     */
    function openCredentialModal(credential) {
        const modal = $('#devnotes-credential-modal');
        const form = $('#devnotes-credential-form')[0];

        form.reset();
        $('#credential-id').val('');

        if (credential) {
            $('#devnotes-modal-title').text(devnotesAdmin.strings.editCredential);
            $('#credential-id').val(credential.id);
            $('#credential-label').val(credential.label);
            $('#credential-type').val(credential.type);
            $('#credential-url').val(credential.url);
            $('#credential-notes').val(credential.notes);

            // Fill in sensitive fields
            if (credential.username) $('#credential-username').val(credential.username);
            if (credential.password) $('#credential-password').val(credential.password);
            if (credential.api_key) $('#credential-api-key').val(credential.api_key);
            if (credential.ssh_key) $('#credential-ssh-key').val(credential.ssh_key);
            if (credential.secure_note) $('#credential-secure-note').val(credential.secure_note);
        } else {
            $('#devnotes-modal-title').text(devnotesAdmin.strings.addCredential);
        }

        updateCredentialFields($('#credential-type').val());
        modal.addClass('active');
    }

    /**
     * Update visible fields based on credential type
     */
    function updateCredentialFields(type) {
        $('.credential-field').hide();
        $(`.credential-field[data-type="${type}"]`).show();
    }

    /**
     * Save credential
     */
    function saveCredential() {
        const form = $('#devnotes-credential-form');
        const data = {
            action: 'devnotes_save_credential',
            nonce: devnotesAdmin.nonce,
            id: $('#credential-id').val(),
            label: $('#credential-label').val(),
            type: $('#credential-type').val(),
            url: $('#credential-url').val(),
            notes: $('#credential-notes').val(),
            username: $('#credential-username').val(),
            password: $('#credential-password').val(),
            api_key: $('#credential-api-key').val(),
            ssh_key: $('#credential-ssh-key').val(),
            secure_note: $('#credential-secure-note').val()
        };

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    loadCredentials();
                } else {
                    alert(response.data.message);
                }
            }
        });
    }

    /**
     * Edit credential
     */
    function editCredential(id) {
        checkVerification(function() {
            $.ajax({
                url: devnotesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'devnotes_get_credential',
                    nonce: devnotesAdmin.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        openCredentialModal(response.data.credential);
                    } else if (response.data.require_verification) {
                        pendingAction = function() { editCredential(id); };
                        showPasswordModal();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });
    }

    /**
     * Delete credential
     */
    function deleteCredential(id) {
        if (!confirm(devnotesAdmin.strings.confirmDelete)) {
            return;
        }

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_delete_credential',
                nonce: devnotesAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    loadCredentials();
                } else {
                    alert(response.data.message);
                }
            }
        });
    }

    /**
     * Reveal credential values
     */
    function revealCredential(id) {
        checkVerification(function() {
            const fieldsContainer = $('#fields-' + id);

            if (fieldsContainer.hasClass('visible')) {
                fieldsContainer.removeClass('visible').html('');
                return;
            }

            $.ajax({
                url: devnotesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'devnotes_get_credential',
                    nonce: devnotesAdmin.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        renderCredentialFields(id, response.data.credential);
                    } else if (response.data.require_verification) {
                        pendingAction = function() { revealCredential(id); };
                        showPasswordModal();
                    }
                }
            });
        });
    }

    /**
     * Render credential fields
     */
    function renderCredentialFields(id, credential) {
        const fieldsContainer = $('#fields-' + id);
        let html = '';

        const fields = {
            'username_password': [
                { key: 'username', label: 'Username' },
                { key: 'password', label: 'Password' }
            ],
            'api_key': [
                { key: 'api_key', label: 'API Key' }
            ],
            'ssh_key': [
                { key: 'ssh_key', label: 'SSH Key' }
            ],
            'secure_note': [
                { key: 'secure_note', label: 'Secure Note' }
            ]
        };

        const typeFields = fields[credential.type] || [];

        typeFields.forEach(function(field) {
            const value = credential[field.key] || '';
            html += `
                <div class="devnotes-field-row">
                    <span class="devnotes-field-label">${field.label}:</span>
                    <span class="devnotes-field-value" data-field="${field.key}">${escapeHtml(value)}</span>
                    <div class="devnotes-field-actions">
                        <button type="button" class="copy-field-btn" data-id="${id}" data-field="${field.key}">Copy</button>
                    </div>
                </div>
            `;
        });

        fieldsContainer.html(html).addClass('visible');

        // Bind copy buttons
        fieldsContainer.find('.copy-field-btn').on('click', function() {
            copyCredentialField($(this).data('id'), $(this).data('field'));
        });
    }

    /**
     * Copy credential to clipboard
     */
    function copyCredential(id) {
        checkVerification(function() {
            $.ajax({
                url: devnotesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'devnotes_get_credential',
                    nonce: devnotesAdmin.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        const cred = response.data.credential;
                        let valueToCopy = '';

                        // Determine primary value based on type
                        switch (cred.type) {
                            case 'username_password':
                                valueToCopy = cred.password || cred.username;
                                break;
                            case 'api_key':
                                valueToCopy = cred.api_key;
                                break;
                            case 'ssh_key':
                                valueToCopy = cred.ssh_key;
                                break;
                            case 'secure_note':
                                valueToCopy = cred.secure_note;
                                break;
                        }

                        if (valueToCopy) {
                            copyToClipboard(valueToCopy);
                            logCopyAction(id, getPrimaryField(cred.type));
                        }
                    } else if (response.data.require_verification) {
                        pendingAction = function() { copyCredential(id); };
                        showPasswordModal();
                    }
                }
            });
        });
    }

    /**
     * Copy specific credential field
     */
    function copyCredentialField(id, field) {
        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_copy_credential',
                nonce: devnotesAdmin.nonce,
                id: id,
                field: field
            },
            success: function(response) {
                if (response.success) {
                    copyToClipboard(response.data.value);
                } else if (response.data.require_verification) {
                    pendingAction = function() { copyCredentialField(id, field); };
                    showPasswordModal();
                }
            }
        });
    }

    /**
     * Get primary field name for credential type
     */
    function getPrimaryField(type) {
        const fields = {
            'username_password': 'password',
            'api_key': 'api_key',
            'ssh_key': 'ssh_key',
            'secure_note': 'secure_note'
        };
        return fields[type] || 'password';
    }

    /**
     * Log copy action
     */
    function logCopyAction(id, field) {
        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_copy_credential',
                nonce: devnotesAdmin.nonce,
                id: id,
                field: field
            }
        });
    }

    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopiedNotice();
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    /**
     * Fallback copy method
     */
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showCopiedNotice();
        } catch (e) {
            alert(devnotesAdmin.strings.copyFailed);
        }
        document.body.removeChild(textarea);
    }

    /**
     * Show copied notification
     */
    function showCopiedNotice() {
        const notice = $('<div class="devnotes-copied-notice">' + devnotesAdmin.strings.copied + '</div>');
        $('body').append(notice);
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 2000);
    }

    /**
     * Initialize drag and drop for reordering
     */
    function initDragAndDrop() {
        const cards = document.querySelectorAll('.devnotes-credential-card');
        let draggedItem = null;

        cards.forEach(function(card) {
            card.addEventListener('dragstart', function(e) {
                draggedItem = this;
                this.classList.add('dragging');
            });

            card.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                document.querySelectorAll('.devnotes-credential-card').forEach(function(c) {
                    c.classList.remove('drag-over');
                });
                saveOrder();
            });

            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });

            card.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });

            card.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedItem !== this) {
                    const container = this.parentNode;
                    const allCards = [...container.querySelectorAll('.devnotes-credential-card')];
                    const draggedIndex = allCards.indexOf(draggedItem);
                    const targetIndex = allCards.indexOf(this);

                    if (draggedIndex < targetIndex) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }
                }
            });
        });
    }

    /**
     * Save credential order
     */
    function saveOrder() {
        const order = [];
        $('.devnotes-credential-card').each(function() {
            order.push($(this).data('id'));
        });

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_reorder_credentials',
                nonce: devnotesAdmin.nonce,
                order: order
            }
        });
    }

    /**
     * Check password verification
     */
    function checkVerification(callback) {
        if (!devnotesAdmin.settings.require_password_verification || isVerified) {
            callback();
            return;
        }

        pendingAction = callback;
        showPasswordModal();
    }

    /**
     * Show password verification modal
     */
    function showPasswordModal() {
        $('#devnotes-password-modal').addClass('active');
        $('#verify-password').val('').focus();
        $('#password-error').hide();
    }

    /**
     * Initialize activity log
     */
    function initActivityLog() {
        if (!devnotesAdmin.canViewCredentials) return;

        $('#devnotes-activity-filter-action').on('change', function() {
            loadActivityLog();
        });
    }

    /**
     * Load activity log
     */
    function loadActivityLog(page) {
        page = page || 1;
        const container = $('#devnotes-activity-log');
        const actionFilter = $('#devnotes-activity-filter-action').val();

        container.html('<div class="devnotes-loading"><div class="devnotes-spinner"></div></div>');

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_get_activity_log',
                nonce: devnotesAdmin.nonce,
                page: page,
                action_type: actionFilter
            },
            success: function(response) {
                if (response.success) {
                    renderActivityLog(response.data);
                }
            }
        });
    }

    /**
     * Render activity log
     */
    function renderActivityLog(data) {
        const container = $('#devnotes-activity-log');
        const pagination = $('#devnotes-activity-pagination');

        if (!data.items || data.items.length === 0) {
            container.html('<div class="devnotes-empty-state"><p>No activity recorded yet.</p></div>');
            pagination.html('');
            return;
        }

        let html = '';
        data.items.forEach(function(log) {
            // Determine the label based on action type
            let label = log.credential_label || '-';
            let details = '';

            // For notes actions, show "Notes" as label and details as extra info
            if (log.action_type.startsWith('notes_')) {
                label = 'Markdown Notes';
                if (log.details) {
                    details = `<div class="devnotes-activity-details">${escapeHtml(log.details)}</div>`;
                }
            }

            html += `
                <div class="devnotes-activity-row">
                    <div class="devnotes-activity-time">${escapeHtml(log.created_at_formatted)}</div>
                    <div class="devnotes-activity-action ${log.action_type}">${escapeHtml(log.action_label)}</div>
                    <div class="devnotes-activity-label">${escapeHtml(label)}${details}</div>
                    <div class="devnotes-activity-user">${escapeHtml(log.user_display_name)}</div>
                </div>
            `;
        });

        container.html(html);

        // Render pagination
        if (data.pages > 1) {
            let paginationHtml = '';
            for (let i = 1; i <= data.pages; i++) {
                paginationHtml += `<button type="button" class="pagination-btn ${i === data.current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
            pagination.html(paginationHtml);

            pagination.find('.pagination-btn').on('click', function() {
                loadActivityLog($(this).data('page'));
            });
        } else {
            pagination.html('');
        }
    }

    /**
     * Initialize settings
     */
    function initSettings() {
        $('#devnotes-settings-form').on('submit', function(e) {
            e.preventDefault();
            saveSettings();
        });
    }

    /**
     * Save settings
     */
    function saveSettings() {
        const userAccess = [];
        $('input[name="user_access[]"]:checked').each(function() {
            userAccess.push($(this).val());
        });

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_save_settings',
                nonce: devnotesAdmin.nonce,
                require_password_verification: $('input[name="require_password_verification"]').is(':checked') ? 1 : 0,
                audit_log_retention_days: $('input[name="audit_log_retention_days"]').val(),
                user_access: userAccess
            },
            success: function(response) {
                if (response.success) {
                    $('#devnotes-settings-status').addClass('saved').text(devnotesAdmin.strings.saved);
                    setTimeout(function() {
                        $('#devnotes-settings-status').removeClass('saved').text('');
                    }, 3000);
                }
            }
        });
    }

    /**
     * Initialize modals
     */
    function initModals() {
        // Close modal buttons
        $('.devnotes-modal-close, .devnotes-modal-cancel').on('click', function() {
            closeModal();
        });

        // Close on backdrop click
        $('.devnotes-modal').on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Password verification form
        $('#devnotes-password-form').on('submit', function(e) {
            e.preventDefault();
            verifyPassword();
        });

        // Close on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('.devnotes-modal').removeClass('active');
        pendingAction = null;
    }

    /**
     * Verify password
     */
    function verifyPassword() {
        const password = $('#verify-password').val();

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_verify_password',
                nonce: devnotesAdmin.nonce,
                password: password
            },
            success: function(response) {
                if (response.success) {
                    isVerified = true;
                    $('#devnotes-password-modal').removeClass('active');

                    if (pendingAction) {
                        pendingAction();
                        pendingAction = null;
                    }
                } else {
                    $('#password-error').text(response.data.message).show();
                }
            }
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Truncate URL for display
     */
    function truncateUrl(url) {
        try {
            const urlObj = new URL(url);
            return urlObj.hostname;
        } catch (e) {
            return url.substring(0, 30) + (url.length > 30 ? '...' : '');
        }
    }

    /**
     * Initialize notes tracking (access logging)
     */
    function initNotesTracking() {
        // Track when user leaves the page with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    /**
     * Log notes access
     */
    function logNotesAccess() {
        // Only log once per page load to avoid spam
        if (notesAccessLogged) return;
        notesAccessLogged = true;

        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_log_notes_access',
                nonce: devnotesAdmin.nonce
            }
        });
    }

    /**
     * Initialize copy/paste tracking on the editor
     */
    function initEditorCopyPasteTracking() {
        const editorContainer = document.getElementById('devnotes-editor');
        if (!editorContainer) return;

        // Track copy events
        editorContainer.addEventListener('copy', function(e) {
            const selection = window.getSelection();
            const selectedText = selection.toString();

            if (selectedText) {
                const fullContent = editor ? editor.getMarkdown() : '';
                const isFullContent = selectedText.length >= fullContent.length * 0.9; // 90% or more

                logNotesCopy(selectedText.length, isFullContent);
            }
        });

        // Track cut events (similar to copy)
        editorContainer.addEventListener('cut', function(e) {
            const selection = window.getSelection();
            const selectedText = selection.toString();

            if (selectedText) {
                const fullContent = editor ? editor.getMarkdown() : '';
                const isFullContent = selectedText.length >= fullContent.length * 0.9;

                logNotesCopy(selectedText.length, isFullContent);
            }
        });

        // Track paste events
        editorContainer.addEventListener('paste', function(e) {
            // Get pasted content
            const clipboardData = e.clipboardData || window.clipboardData;
            if (clipboardData) {
                const pastedText = clipboardData.getData('text');
                const pastedHtml = clipboardData.getData('text/html');

                const pasteLength = pastedText ? pastedText.length : 0;
                const pasteType = pastedHtml ? 'rich text' : 'plain text';

                if (pasteLength > 0) {
                    logNotesPaste(pasteLength, pasteType);
                }
            }
        });

        // Also track keyboard shortcuts for copy (Ctrl+C / Cmd+C)
        editorContainer.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                const selection = window.getSelection();
                const selectedText = selection.toString();

                if (selectedText) {
                    const fullContent = editor ? editor.getMarkdown() : '';
                    const isFullContent = selectedText.length >= fullContent.length * 0.9;

                    // Small delay to let the copy happen first
                    setTimeout(function() {
                        logNotesCopy(selectedText.length, isFullContent);
                    }, 100);
                }
            }
        });
    }

    /**
     * Log notes copy action
     */
    function logNotesCopy(selectionLength, isFullContent) {
        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_log_notes_copy',
                nonce: devnotesAdmin.nonce,
                selection_length: selectionLength,
                is_full_content: isFullContent ? 'true' : 'false'
            }
        });
    }

    /**
     * Log notes paste action
     */
    function logNotesPaste(pasteLength, pasteType) {
        $.ajax({
            url: devnotesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'devnotes_log_notes_paste',
                nonce: devnotesAdmin.nonce,
                paste_length: pasteLength,
                paste_type: pasteType
            }
        });
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
