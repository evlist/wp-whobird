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
    // Check if eBird ID is provided
    if ( ! isset( $_POST['ebird_id'] ) || empty( $_POST['ebird_id'] ) ) {
        wp_send_json_error( [ 'message' => __( 'Missing eBird ID.', 'wp-whobird' ) ] );
        return;
    }

    $ebird_id = sanitize_text_field( $_POST['ebird_id'] );

    // Initialize the classes
    $wikidata_query = new WikidataQuery();
    $bird_renderer  = new BirdListItemRenderer( $ebird_id, $wikidata_query );

    // Fetch the bird data
    $fresh_data = $wikidata_query->fetchAndUpdateCachedData( $ebird_id );

    if ( ! $fresh_data ) {
        wp_send_json_error( [ 'message' => __( 'Could not fetch bird data.', 'wp-whobird' ) ] );
        return;
    }

    // Render the HTML for the bird entry
    $html = $bird_renderer->renderBirdData( $fresh_data, false );

    wp_send_json_success( [ 'html' => $html ] );
}
add_action( 'wp_ajax_update_bird_data', __NAMESPACE__ . '\\update_bird_data' );
add_action( 'wp_ajax_nopriv_update_bird_data', __NAMESPACE__ . '\\update_bird_data' );
