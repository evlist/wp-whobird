<?php
/**
 * Handles the Ajax request to update bird data based on eBird ID.
 */

namespace WPWhoBird;

require_once plugin_dir_path( __FILE__ ) . '/WikidataQuery.php';
require_once plugin_dir_path( __FILE__ ) . '/BirdListItemRenderer.php';


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
    // Check if eBird ID is provided
    if ( ! isset( $_POST['ebird_id'] ) || empty( $_POST['ebird_id'] ) ) {
        wp_send_json_error( [ 'message' => __( 'Missing eBird ID.', 'wp-whobird' ) ] );
        return;
    }

    $ebird_id = sanitize_text_field( $_POST['ebird_id'] );

    // Initialize the classes
    $bird_renderer  = new BirdListItemRenderer( $ebird_id );

    // Fetch the bird data
    $wikidataQuery = new WikidataQuery($locale ?? get_locale());

    error_log('Step 1 - Initializations: ' . (microtime(true) - $startTime) . ' seconds');

    $fresh_data = $wikidataQuery->fetchAndUpdateCachedData( $ebird_id );

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
