<?php
/**
 * Notes class for Briefnote
 *
 * @package Briefnote
 * @since 1.0.0
 * @license GPL-2.0-or-later
 *
 * This file is part of Briefnote.
 *
 * Briefnote is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Briefnote is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Briefnote Notes Class
 *
 * Handles Markdown notes storage and retrieval
 */
class Briefnote_Notes {

    /**
     * Option name for notes content
     */
    const OPTION_CONTENT = 'briefnote_content';

    /**
     * Option name for last saved timestamp
     */
    const OPTION_LAST_SAVED = 'briefnote_last_saved';

    /**
     * Get notes content
     *
     * @return string
     */
    public static function get_content() {
        return get_option( self::OPTION_CONTENT, '' );
    }

    /**
     * Save notes content
     *
     * @param string $content The Markdown content to save
     * @return bool True on success, false on failure
     */
    public static function save_content( $content ) {
        // Sanitize content - allow HTML since Markdown may contain it
        $content = wp_kses_post( $content );

        $saved = update_option( self::OPTION_CONTENT, $content );

        if ( $saved || get_option( self::OPTION_CONTENT ) === $content ) {
            // Update last saved timestamp
            $timestamp = current_time( 'mysql' );
            update_option( self::OPTION_LAST_SAVED, $timestamp );
            return true;
        }

        return false;
    }

    /**
     * Get last saved timestamp
     *
     * @return string|false Timestamp string or false if never saved
     */
    public static function get_last_saved() {
        $timestamp = get_option( self::OPTION_LAST_SAVED, '' );
        return ! empty( $timestamp ) ? $timestamp : false;
    }

    /**
     * Get last saved timestamp formatted for display
     *
     * @return string Formatted timestamp or "Never" if never saved
     */
    public static function get_last_saved_formatted() {
        $timestamp = self::get_last_saved();

        if ( ! $timestamp ) {
            return __( 'Never', 'briefnote' );
        }

        $datetime = strtotime( $timestamp );
        $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

        return date_i18n( $format, $datetime );
    }

    /**
     * Check if user can view notes (read-only or editable)
     *
     * @return bool
     */
    public static function current_user_can_view() {
        return current_user_can( 'manage_options' )
            || current_user_can( BRIEFNOTE_VIEW_NOTES_CAP )
            || current_user_can( BRIEFNOTE_EDIT_NOTES_CAP );
    }

    /**
     * Check if user can edit notes
     *
     * @return bool
     */
    public static function current_user_can_edit() {
        return current_user_can( 'manage_options' )
            || current_user_can( BRIEFNOTE_EDIT_NOTES_CAP );
    }
}
