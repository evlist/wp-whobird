<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Wikidata Query
 *
 * Provides a class for querying and caching Wikidata information for bird species
 * using BirdNET integer IDs and Wikidata Q-ids.
 * Handles SPARQL query building, cURL execution, caching, and data formatting
 * for the whoBIRD WordPress plugin.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

namespace WPWhoBird;

use WPWhoBird\Config;

require_once 'SparqlUtils.php';
require_once 'ImageUtils.php';
require_once 'FileLockThrottle.php';

/**
 * Class WikidataQuery
 *
 * Handles querying Wikidata for species data, caching results, and providing
 * formatted output for the plugin.
 */
class WikidataQuery {
    /** @var int Minimum interval (in milliseconds) between requests */
    private static $requestIntervalMs = 50;
    /** @var string User locale (e.g., 'en_US') */
    private string $locale;
    /** @var string User language code (e.g., 'en') */
    private string $language;

    /**
     * Constructor.
     * @param string $locale The user locale string (e.g., 'fr_FR').
     */
    public function __construct($locale)
    {
        $this->locale = $locale;
        $this->language = substr($locale, 0, 2); // Extract the language code (e.g., 'fr', 'es')
    }

    /**
     * Get cached Wikidata info for a bird given its BirdNET integer ID.
     *
     * @param int $birdnetId
     * @return array|null Returns ['isFresh' => bool, 'data' => array|null] or null if not cached
     */
    public function getCachedData(int $birdnetId): ?array {
        global $wpdb;
        $tableName = Config::getTableSparqlCache();

        $cachedResult = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT result, expiration FROM $tableName WHERE birdnet_id = %d",
                    $birdnetId
                    ),
                ARRAY_A
                );

        if (!$cachedResult) {
            return null;
        }

        $isFresh = strtotime($cachedResult['expiration']) > time();

        return [
            'isFresh' => $isFresh,
            'data' => json_decode($cachedResult['result'], true),
        ];
    }

    /**
     * Fetch data from Wikidata and update the cache. Requires birdnet_id and wikidata_qid.
     *
     * @param int $birdnetId
     * @param string $wikidataId
     * @return array Result array with keys: commonName, description, latinName, originalImage, image, wikipedia
     */
    public function fetchAndUpdateCachedData(int $birdnetId, string $wikidataId): array {

        $throttle = new FileLockThrottle("wikidata", self::$requestIntervalMs);
        $throttle->waitUntilAllowed();

        $cachedData = $this->getCachedData($birdnetId);
        if ($cachedData && $cachedData['isFresh']) {
            return $cachedData['data'];
        }

        $sparqlQuery = $this->buildSparqlQuery($wikidataId);
#        error_log('sparqlQuery: ' . $sparqlQuery);
        $sparqlUrl = "https://query.wikidata.org/sparql?query=" . urlencode($sparqlQuery);
        $sparqlHeaders = ["Accept: application/json"];
        $startCurl = microtime(true);
        $sparqlResponse = $this->executeCurl($sparqlUrl, $sparqlHeaders);
        error_log('cURL execution time: ' . (microtime(true) - $startCurl) . ' seconds');
        $sparqlData = json_decode($sparqlResponse, true);

#        error_log('sparqlData: ' . print_r($sparqlData, true));
        return $this->processAndCacheData($birdnetId, $sparqlData);
    }

    /**
     * Build a SPARQL query to fetch the label, description, latin name, main alias (in user's language, not latin name),
     * image, and Wikipedia link for a Wikidata entity.
     * Prefers label as the common name if different from the latin name,
     * otherwise falls back to an alias, then the latin name.
     *
     * @param string $wikidataId The Wikidata Q-id (e.g., "Q5113")
     * @return string SPARQL query string
     * @throws \InvalidArgumentException
     */
    private function buildSparqlQuery(string $wikidataId): string {
        if (!preg_match('/^Q\d+$/', $wikidataId)) {
            throw new \InvalidArgumentException("Invalid Wikidata Q-id: $wikidataId");
        }
        return <<<SPARQL
            SELECT ?itemLabel ?itemDescription ?latinName ?alias ?image ?wikipedia WHERE {
                BIND(wd:$wikidataId AS ?item)
                    OPTIONAL { ?item wdt:P225 ?latinName. }
                    # Subquery: Get the first alias in the desired language that is not the latin name
                OPTIONAL {
                    SELECT ?alias WHERE {
                    wd:$wikidataId skos:altLabel ?alias .
                    FILTER(LANG(?alias) = "{$this->language}")
                    OPTIONAL { wd:$wikidataId wdt:P225 ?latinName. }
                    FILTER(?alias != ?latinName)
                    }
                    LIMIT 1
                }
                OPTIONAL { ?item wdt:P18 ?image. }
                OPTIONAL {
                    ?wikipedia schema:about ?item;
                    schema:isPartOf <https://{$this->language}.wikipedia.org/>.
                }
                SERVICE wikibase:label { bd:serviceParam wikibase:language "{$this->language},en". }
            }
            LIMIT 1
            SPARQL;
    }

    /**
     * Process SPARQL results and cache them in the database.
     * Prefers the label as the common name (if not the latin name), falls back to alias, then latin name.
     *
     * @param int $birdnetId BirdNET integer ID
     * @param array $sparqlData Decoded SPARQL JSON result
     * @return array Result array with keys: commonName, description, latinName, originalImage, image, wikipedia
     */
    private function processAndCacheData(int $birdnetId, array $sparqlData): array {
        global $wpdb;

        $result = null;
        if (!empty($sparqlData['results']['bindings'])) {
            $binding = $sparqlData['results']['bindings'][0];
            $latinName = $binding['latinName']['value'] ?? null;
            $itemLabel = $binding['itemLabel']['value'] ?? null;
            $alias = $binding['alias']['value'] ?? null;

            // Use the label as the common name if it's different from the latin name.
            // If the label is missing or is the latin name, use the first alias (if available).
            // Otherwise, fall back to the latin name.
            if ($itemLabel && (!$latinName || $itemLabel !== $latinName)) {
                $commonName = $itemLabel;
            } elseif ($alias) {
                $commonName = $alias;
            } else {
                $commonName = $latinName;
            }

            $result = [
                'commonName' => $commonName, // Common name in user's language, using the best available source
                'description' => $binding['itemDescription']['value'] ?? null, // Short descriptor
                'latinName' => $latinName, // Scientific name (P225)
                'originalImage' => $binding['image']['value'] ?? null, // Original image URL, if any
                'image' => $binding['image']['value'] ? resolveSpecialFilePathUrl($binding['image']['value']) : null, // Resolved image URL for display
                'wikipedia' => $binding['wikipedia']['value'] ?? null, // Wikipedia page in the user's language, if available
            ];
        }

        $tableName = Config::getTableSparqlCache();

        $min = 7 * 24 * 60 * 60;   // 7 days in seconds
        $max = 14 * 24 * 60 * 60;  // 14 days in seconds
        $randSeconds = rand($min, $max);

        // Cache the result in the database with random expiration to avoid thundering herd
        $wpdb->replace(
                $tableName,
                [
                'birdnet_id' => $birdnetId,
                'result' => json_encode($result),
                'expiration' => date('Y-m-d H:i:s', time() + $randSeconds),
                ]
                );

        return $result;
    }

    /**
     * Execute a cURL request and return the response.
     *
     * @param string $url The URL to request.
     * @param array $headers Optional HTTP headers for the request.
     * @return string Response data.
     */
    private function executeCurl($url, $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // User-Agent header
        $defaultHeaders = [
            "User-Agent: wp-whobird/0.1 (https://github.com/evlist/wp-whobird ; vdv@dyomedea.com)"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}

