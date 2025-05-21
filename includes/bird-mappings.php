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
