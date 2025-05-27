<?php
// vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0

namespace WPWhoBird;

use WPWhoBird\Config;

require_once 'SparqlUtils.php';
require_once 'ImageUtils.php';
require_once 'FileLockThrottle.php';

class WikidataQuery {
    private static $requestIntervalMs = 50; // Minimum interval (in milliseconds) between requests
    private string $locale;
    private string $language;

    public function __construct($locale)
    {
        $this->locale = $locale;
        $this->language = substr($locale, 0, 2); // Extract the language code (e.g., 'fr', 'es')
    }

    /**
     * Get cached Wikidata info for a bird given its BirdNET integer ID.
     * @param int $birdnetId
     * @return array|null
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
     * @param int $birdnetId
     * @param string $wikidataId
     * @return array
     */
    public function fetchAndUpdateCachedData(int $birdnetId, string $wikidataId): array {

        $throttle = new FileLockThrottle("wikidata", self::$requestIntervalMs);
        $throttle->waitUntilAllowed();

        $cachedData = $this->getCachedData($birdnetId);
        if ($cachedData && $cachedData['isFresh']) {
            return $cachedData['data'];
        }

        $sparqlQuery = $this->buildSparqlQuery($wikidataId);
        error_log('sparqlQuery: ' . $sparqlQuery);
        $sparqlUrl = "https://query.wikidata.org/sparql?query=" . urlencode($sparqlQuery);
        $sparqlHeaders = ["Accept: application/json"];
        $startCurl = microtime(true);
        $sparqlResponse = $this->executeCurl($sparqlUrl, $sparqlHeaders);
        error_log('cURL execution time: ' . (microtime(true) - $startCurl) . ' seconds');
        $sparqlData = json_decode($sparqlResponse, true);

        return $this->processAndCacheData($birdnetId, $sparqlData);
    }

    /**
     * Build a minimal SPARQL query using the Wikidata Q-id.
     * @param string $wikidataId e.g. "Q5113"
     * @return string
     */
    private function buildSparqlQuery(string $wikidataId): string {
        return <<<SPARQL
            SELECT ?itemLabel ?itemDescription ?latinName ?image ?wikipedia WHERE {
                BIND(wd:$wikidataId AS ?item)
                    OPTIONAL { ?item wdt:P225 ?latinName. }
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
     * Store the fetched data in the cache.
     * @param int $birdnetId
     * @param array $sparqlData
     * @return array
     */
    private function processAndCacheData(int $birdnetId, array $sparqlData): array {
        global $wpdb;

        $result = null;
        if (!empty($sparqlData['results']['bindings'])) {
            $binding = $sparqlData['results']['bindings'][0];
            $result = [
                'commonName' => $binding['itemLabel']['value'] ?? null,
                'description' => $binding['itemDescription']['value'] ?? null,
                'latinName' => $binding['latinName']['value'] ?? null,
                'originalImage' => $binding['image']['value'] ?? null,
                'image' => $binding['image']['value'] ? resolveSpecialFilePathUrl($binding['image']['value']) : null,
                'wikipedia' => $binding['wikipedia']['value'] ?? null,
            ];
        }

        $tableName = Config::getTableSparqlCache();

        $min = 7 * 24 * 60 * 60;   // 7 days in seconds
        $max = 14 * 24 * 60 * 60;  // 14 days in seconds
        $randSeconds = rand($min, $max);

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
