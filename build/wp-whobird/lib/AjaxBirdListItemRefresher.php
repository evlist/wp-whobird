<?php
/**
 * Handles the Ajax request to update bird data based on BirdNET ID.
 */

namespace WPWhoBird;

require_once plugin_dir_path( __FILE__ ) . '/WikidataQuery.php';
require_once plugin_dir_path( __FILE__ ) . '/BirdListItemRenderer.php';

use WPWhoBird\Config;
use WPWhoBird\WikidataQuery;
use WPWhoBird\BirdListItemRenderer;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajax handler for updating bird data.
 */
function update_bird_data() {
    $startTime = microtime(true);

    // Check if BirdNET ID is provided
    if ( ! isset( $_POST['birdnet_id'] ) || empty( $_POST['birdnet_id'] ) ) {
        wp_send_json_error( [ 'message' => __( 'Missing BirdNET ID.', 'wp-whobird' ) ] );
        return;
    }

    $birdnet_id = (int) $_POST['birdnet_id'];

    // Optional: Handle locale if passed
    $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : get_locale();

    // Look up the Wikidata ID (Q-id) for this BirdNET ID
    global $wpdb;
    $mapping_table = Config::getTableMapping();
    $wikidata_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT wikidata_id FROM $mapping_table WHERE birdnet_id = %d",
            $birdnet_id
        )
    );

    if (empty($wikidata_id)) {
        wp_send_json_error( [ 'message' => __( 'Could not find Wikidata ID for this BirdNET ID.', 'wp-whobird' ) ] );
        return;
    }

    // Initialize the classes
    $bird_renderer  = new BirdListItemRenderer( $birdnet_id, $locale );
    $wikidataQuery = new WikidataQuery($locale);

    error_log('Step 1 - Initializations: ' . (microtime(true) - $startTime) . ' seconds');

    $fresh_data = $wikidataQuery->fetchAndUpdateCachedData( $birdnet_id, $wikidata_id );

    error_log('Step 2 - Data fetched: ' . (microtime(true) - $startTime) . ' seconds');

    if ( ! $fresh_data ) {
        wp_send_json_error( [ 'message' => __( 'Could not fetch bird data.', 'wp-whobird' ) ] );
        return;
    }

    // Render the HTML for the bird entry
    $html = $bird_renderer->renderBirdData( $fresh_data );

    error_log('Step 3 - HTML rendered: ' . (microtime(true) - $startTime) . ' seconds');

    wp_send_json_success( [ 'html' => $html ] );

    error_log('Total execution time: ' . (microtime(true) - $startTime) . ' seconds');
}

add_action( 'wp_ajax_update_bird_data', __NAMESPACE__ . '\\update_bird_data' );
add_action( 'wp_ajax_nopriv_update_bird_data', __NAMESPACE__ . '\\update_bird_data' );

