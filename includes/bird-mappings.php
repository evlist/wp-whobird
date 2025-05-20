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
        'label' => 'BirdNET species file (GLOBAL 6K V2.4, en-uk)',
        'description' => 'BirdNET species list (ID, scientific name, common name, etc.) from the official BirdNET-Analyzer repository.',
        'github_repo' => 'birdnet-team/BirdNET-Analyzer',
        'github_path' => 'birdnet_analyzer/labels/V2.4/BirdNET_GLOBAL_6K_V2.4_Labels_en_uk.txt',
        'raw_url' => 'https://raw.githubusercontent.com/birdnet-team/BirdNET-Analyzer/main/birdnet_analyzer/labels/V2.4/BirdNET_GLOBAL_6K_V2.4_Labels_en_uk.txt',
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
