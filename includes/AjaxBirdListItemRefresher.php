<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * AjaxBirdListItemRefresher
 *
 * Handles AJAX requests to fetch and refresh bird list item details via BirdNET and Wikidata.
 *
 * Expects POST parameter 'birdnet_id' (positive integer). The locale is always the
 * site locale: the browser's choice is not trusted (the cache is keyed by BirdNET ID only).
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
 * Expects POST parameter 'birdnet_id' (positive integer). A fresh cache entry is answered
 * from the cache without any throttle wait or network request.
 * Outputs JSON (success or error).
 *
 * @return void
 */
function update_bird_data()
{
    $startTime = microtime(true);

    // Validate and sanitize input parameters
    $birdnet_id = isset($_POST['birdnet_id']) ? absint(wp_unslash($_POST['birdnet_id'])) : 0;
    if ($birdnet_id <= 0) {
        wp_send_json_error(['message' => __('Missing BirdNET ID.', 'wp-whobird')]);
        return;
    }

    // The locale is the site's, never the browser's: the cache is keyed by BirdNET ID only.
    $locale = get_locale();

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

    error_log(sprintf(__('Step 1 - Initializations: %s seconds', 'wp-whobird'), (microtime(true) - $startTime)));

    // A fresh cache entry is enough: no throttle wait, no network request.
    $cache = $wikidataQuery->getCachedData($birdnet_id);
    if ($cache && $cache['isFresh'] && !empty($cache['data'])) {
        $fresh_data = $cache['data'];
    } else {
        // Fetch latest bird data from Wikidata (throttled, and cached).
        $fresh_data = $wikidataQuery->fetchAndUpdateCachedData($birdnet_id, $wikidata_qid);
    }

    error_log(sprintf(__('Step 2 - Data fetched: %s seconds', 'wp-whobird'), (microtime(true) - $startTime)));

    if (!$fresh_data) {
        wp_send_json_error(['message' => __('Could not fetch bird data.', 'wp-whobird')]);
        return;
    }

    // Render HTML output for the updated bird entry
    $html = $bird_renderer->renderBirdData($fresh_data);

    error_log(sprintf(__('Step 3 - HTML rendered: %s seconds', 'wp-whobird'), (microtime(true) - $startTime)));

    // Return JSON response with rendered HTML
    wp_send_json_success(['html' => $html]);

    error_log(sprintf(__('Total execution time: %s seconds', 'wp-whobird'), (microtime(true) - $startTime)));
}

// Register AJAX handlers for logged-in and non-logged-in users
add_action('wp_ajax_update_bird_data', __NAMESPACE__ . '\\update_bird_data');
add_action('wp_ajax_nopriv_update_bird_data', __NAMESPACE__ . '\\update_bird_data');
