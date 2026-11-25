<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Actions of the "Mapping Sources" admin panel (update a source file, update the
 * mapping table from a source).
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

if (!defined('ABSPATH')) exit;

/** Action and field name of the nonce of the Mapping Sources form. */
const WHOBIRD_MAPPING_SOURCES_NONCE_ACTION = 'whobird_mapping_sources_action';
const WHOBIRD_MAPPING_SOURCES_NONCE_FIELD  = 'whobird_mapping_sources_nonce';

/**
 * Processes a POST request of the Mapping Sources form and returns the notices HTML.
 *
 * Requires the manage_options capability and a valid nonce; does nothing (and
 * returns an empty string) when the request does not ask for an action, and
 * returns an error notice when it is not allowed.
 *
 * @param array $post      The POST data (normally $_POST).
 * @param array $sources   Source configurations, keyed by source key (labels).
 * @param array $instances Source objects, keyed by source key, with update() and uploadToTable().
 * @return string HTML notices.
 */
function whobird_process_mapping_sources_request(array $post, array $sources, array $instances): string {
    $actions = [];
    foreach ($instances as $key => $instance) {
        if (isset($post['update_' . $key])) {
            $actions[] = ['update', $key];
        }
        if (isset($post['update_table_' . $key])) {
            $actions[] = ['update_table', $key];
        }
    }
    if (!$actions) {
        return '';
    }

    $nonce = isset($post[WHOBIRD_MAPPING_SOURCES_NONCE_FIELD])
        ? sanitize_text_field(wp_unslash($post[WHOBIRD_MAPPING_SOURCES_NONCE_FIELD]))
        : '';
    if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, WHOBIRD_MAPPING_SOURCES_NONCE_ACTION)) {
        return '<div class="notice notice-error is-dismissible"><p>'
            . esc_html__('You are not allowed to do this, or the page has expired. Please reload it and try again.', 'wp-whobird')
            . '</p></div>';
    }

    $html = '';
    foreach ($actions as [$action, $key]) {
        $label = $sources[$key]['label'] ?? $key;
        if ($action === 'update') {
            // Handle source file update.
            list($ok, $msg) = $instances[$key]->update();
            $html .= '<div class="' . ($ok ? 'updated notice' : 'notice notice-error is-dismissible') . '"><p>'
                . esc_html($label . ': ' . __($msg, 'wp-whobird')) . '</p></div>';
        } else {
            // Handle mapping table update from source.
            list($ok, $msg) = $instances[$key]->uploadToTable();
            $html .= '<div class="' . ($ok ? 'updated notice' : 'notice notice-error is-dismissible') . '"><p>'
                . sprintf(
                    // Translators: %s is mapping source label.
                    esc_html__('Update table (%s): ', 'wp-whobird'),
                    esc_html($label)
                ) . esc_html__($msg, 'wp-whobird') . '</p></div>';
        }
    }
    return $html;
}
