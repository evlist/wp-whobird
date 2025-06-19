<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Registers the AJAX handler for generating the mapping in the whoBIRD plugin.
 * This should be loaded by the main plugin file.
 */
add_action('wp_ajax_whobird_generate_mapping', 'whobird_ajax_generate_mapping_handler');

require_once __DIR__ . '/bird-mappings.php';

/**
 * Handles the AJAX request to generate mapping steps for Whobird.
 *
 * - Validates the AJAX nonce for security.
 * - Determines the current mapping step, either from the POST data or defaults to the first step.
 * - Executes the corresponding mapping step.
 * - Returns a JSON response indicating success or failure, and the next step if applicable.
 *
 * Expected POST parameters:
 *   - 'step' (optional): The name of the current mapping step to execute.
 *
 * @return void Outputs a JSON response and terminates execution.
 */
function whobird_ajax_generate_mapping_handler() {
    check_ajax_referer('whobird-generate-mapping');

    // Get all mapping steps and initialize variables.
    $all_steps = array_keys(whobird_get_mapping_steps());
    $step_index = 0;
    $step = '';

    // Determine the current step from AJAX request (by index or step name).
    if (isset($_POST['step'])) {
        $step = sanitize_text_field($_POST['step']);
        $step_index = array_search($step, $all_steps, true);
        if ($step_index === false) {
            wp_send_json_error(['msg' => 'Unknown step.']);
        }
    } else {
        // If no step provided, start from the first step.
        $step = $all_steps[0];
        $step_index = 0;
    }

    // Execute the selected mapping step.
    $result = whobird_execute_mapping_step($step);

    if ($result['success']) {
        // Determine the next step if available.
        $next_step = null;
        if ($step_index !== false && $step_index + 1 < count($all_steps)) {
            $next_step = $all_steps[$step_index + 1];
        }
        wp_send_json_success([
            'msg' => $result['msg'],
            'next_step' => $next_step,
        ]);
    } else {
        wp_send_json_error([
            'msg' => $result['msg'],
        ]);
    }
}
