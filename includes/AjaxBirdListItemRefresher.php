<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * AjaxBirdListItemRefresher
 *
 * Handles AJAX requests to fetch and refresh bird list item details via BirdNET and Wikidata.
 *
 * Expects POST parameter 'birdnet_id' (integer), and optional 'locale' (string).
 * Outputs a JSON response with rendered HTML for the bird entry or an error message.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

namespace WPWhoBird;

require_once plugin_dir_path(__FILE__) . '/WikidataQuery.php';
require_once plugin_dir_path(__FILE__) . '/BirdListItemRenderer.php';

use WPWhoBird\Config;
use WPWhoBird\WikidataQuery;
use WPWhoBird\BirdListItemRenderer;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the AJAX request to update bird data by BirdNET ID.
 *
 * Expects POST parameter 'birdnet_id' (integer) and optional 'locale' (string).
 * Outputs JSON (success or error).
 *
 * @return void
 */
function update_bird_data()
{
    $startTime = microtime(true);

    // Validate and sanitize input parameters
    if (!isset($_POST['birdnet_id']) || empty($_POST['birdnet_id'])) {
        wp_send_json_error(['message' => __('Missing BirdNET ID.', 'wp-whobird')]);
        return;
    }

    $birdnet_id = (int) $_POST['birdnet_id'];

    // Optionally handle locale if passed, fallback to site locale
    $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : get_locale();

    // Retrieve Wikidata QID for the given BirdNET ID
    global $wpdb;
    $mapping_table = Config::getTableMapping();
    $wikidata_qid = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT wikidata_qid FROM $mapping_table WHERE birdnet_id = %d",
            $birdnet_id
        )
    );

    if (empty($wikidata_qid)) {
        wp_send_json_error(['message' => __('Could not find Wikidata ID for this BirdNET ID.', 'wp-whobird')]);
        return;
    }

    // Initialize renderer and Wikidata query client
    $bird_renderer  = new BirdListItemRenderer($birdnet_id, $locale);
    $wikidataQuery = new WikidataQuery($locale);

    error_log('Step 1 - Initializations: ' . (microtime(true) - $startTime) . ' seconds');

    // Fetch latest bird data from Wikidata (with caching)
    $fresh_data = $wikidataQuery->fetchAndUpdateCachedData($birdnet_id, $wikidata_qid);

    error_log('Step 2 - Data fetched: ' . (microtime(true) - $startTime) . ' seconds');

    if (!$fresh_data) {
        wp_send_json_error(['message' => __('Could not fetch bird data.', 'wp-whobird')]);
        return;
    }

    // Render HTML output for the updated bird entry
    $html = $bird_renderer->renderBirdData($fresh_data);

    error_log('Step 3 - HTML rendered: ' . (microtime(true) - $startTime) . ' seconds');

    // Return JSON response with rendered HTML
    wp_send_json_success(['html' => $html]);

    error_log('Total execution time: ' . (microtime(true) - $startTime) . ' seconds');
}

// Register AJAX handlers for logged-in and non-logged-in users
add_action('wp_ajax_update_bird_data', __NAMESPACE__ . '\\update_bird_data');
add_action('wp_ajax_nopriv_update_bird_data', __NAMESPACE__ . '\\update_bird_data');

