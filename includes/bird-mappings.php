<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/WhoBirdSources.php';

// ---- CONFIGURATION ----

$WHOBIRD_MAPPING_SOURCES = [
    'taxo_code' => [
        'label' => 'whoBIRD taxo_code.txt',
        'description' => 'Maps BirdNET IDs to eBird IDs',
        'github_repo' => 'woheller69/whoBIRD',
        'github_path' => 'app/src/main/assets/taxo_code.txt',
        'raw_url' => 'https://github.com/woheller69/whoBIRD/raw/master/app/src/main/assets/taxo_code.txt',
    ],
    'birdnet_species' => [
        'label' => 'whoBIRD BirdNET species file (labels_en.txt)',
        'description' => 'BirdNET species list (ID, scientific name, common name, etc.) from whoBIRD, kept in sync with taxo_code.txt.',
        'github_repo' => 'woheller69/whoBIRD',
        'github_path' => 'app/src/main/assets/labels_en.txt',
        'raw_url' => 'https://github.com/woheller69/whoBIRD/raw/master/app/src/main/assets/labels_en.txt',
    ],
    'wikidata_species' => [
        'label' => 'Wikidata birds SPARQL export (English names, eBird IDs)',
        'description' => 'Bird species exported from Wikidata via SPARQL. Includes Wikidata Q ID, English common name, scientific name, taxon rank, and eBird taxon ID.',
        'sparql_url' => 'https://query.wikidata.org/sparql',
        'query' => <<<SPARQL
SELECT ?item ?itemLabel ?scientificName ?taxonRankLabel ?eBirdID WHERE {
  ?item wdt:P105 wd:Q7432.  # Taxon (species or below)
  ?item wdt:P225 ?scientificName.  # Scientific name
  OPTIONAL { ?item wdt:P3444 ?eBirdID. }  # eBird ID
  OPTIONAL { ?item wdt:P105 ?taxonRank. }  # Taxon rank
  ?item wdt:P171* wd:Q5113.  # Descendant of Aves (birds)
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
ORDER BY ?scientificName
SPARQL,
    ],
];

// ---- DATABASE TABLE ----

global $wpdb;
$WHOBIRD_MAPPING_TABLE = $wpdb->prefix . 'whobird_remote_files';

/**
 * Generate or update the main mapping table for BirdNET species and Wikidata IDs.
 */
function whobird_generate_mapping_table() {
    global $wpdb;

    $mapping_table = $wpdb->prefix . 'whobird_mapping';

    // Drop and recreate the mapping table
    $wpdb->query("DROP TABLE IF EXISTS {$mapping_table}");
    $wpdb->query("
        CREATE TABLE {$mapping_table} (
            birdnet_id INT(10) UNSIGNED PRIMARY KEY,
            scientific_name VARCHAR(128),
            wikidata_id VARCHAR(64)
        )
    ");

    // Step 1: Insert birdnet_id and scientific_name from birdnet_species
    $wpdb->query("
        INSERT INTO {$mapping_table} (birdnet_id, scientific_name)
        SELECT birdnet_id, scientific_name FROM {$wpdb->prefix}whobird_birdnet_species
    ");

    // Step 2: Update wikidata_id via scientific name
    $wpdb->query("
        UPDATE {$mapping_table} m
        JOIN {$wpdb->prefix}whobird_wikidata_species w ON m.scientific_name = w.scientificName
        SET m.wikidata_id = w.item
    ");

    // Step 3: Update wikidata_id via eBird ID for remaining rows
    $wpdb->query("
        UPDATE {$mapping_table} m
        JOIN {$wpdb->prefix}whobird_taxocode t ON m.birdnet_id = t.birdnet_id
        JOIN {$wpdb->prefix}whobird_wikidata_species w ON t.ebird_id = w.eBirdID
        SET m.wikidata_id = w.item
        WHERE m.wikidata_id IS NULL
    ");
}

// Register the action handler for admin-post
add_action('admin_post_whobird_generate_mapping_table', function() {
    check_admin_referer('whobird-generate-mapping');
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    whobird_generate_mapping_table();
    wp_redirect(add_query_arg('mapping_updated', '1', wp_get_referer()));
    exit;
});
