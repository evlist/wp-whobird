<?php
/**
 * Handles the management of the custom table for storing data from the taxo_code.txt file.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

/**
 * Function triggered during plugin activation.
 */
/**
 * Creates or recreates the custom table and populates it only if the source file exists.
 */
function taxoCodeTableInit() {
    global $wpdb;

    // Define the table name
    $table_name = $wpdb->prefix . 'whobird_taxo_codes';

    // Path to the taxo_code.txt file
    $file_path = plugin_dir_path( __FILE__ ) . '../../assets/data/taxo_code.txt';

    // Check if the file exists
    if ( ! file_exists( $file_path ) ) {
        // If the file does not exist, log an error and leave the database as is
        error_log( "The file $file_path was not found. The table $table_name was not modified." );
        return;
    }

    // Drop the table if it exists
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

    // Create the table with line_number as the primary key
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        birdnet_id INT NOT NULL PRIMARY KEY, -- Use line_number as the primary key
        ebird_id TEXT NOT NULL
    ) $charset_collate;";

    // Include the WordPress upgrade script for database operations
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Read the file line by line
    $lines = file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

    if ( $lines ) {
        // Insert each line into the table
        foreach ( $lines as $line_number => $content ) {
            $wpdb->insert(
                $table_name,
                [
                    'birdnet_id' => $line_number , // Line numbers start from 0
                    'ebird_id'     => $content,
                ],
                [ '%d', '%s' ]
            );
        }
    }
}

/**
 * Retrieves the ebirdId for a given birdnetId.
 *
 * @param int $birdnetId
 * @return string|null The corresponding ebirdId or null if not found.
 */
function getEbirdIdByBirdnetId(int $birdnetId): ?string {
    global $wpdb;

    $table_name = $wpdb->prefix . 'whobird_taxo_codes';
    $query = $wpdb->prepare("SELECT ebird_id FROM $table_name WHERE birdnet_id = %d", $birdnetId);

    return $wpdb->get_var($query) ?: null;
}
