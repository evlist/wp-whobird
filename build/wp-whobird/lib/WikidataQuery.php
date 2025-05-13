<?php
// vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0

namespace WPWhoBird;

use WPWhoBird\Config;

require_once 'SparqlUtils.php';
require_once 'ImageUtils.php';

class WikidataQuery {
    private static $lastRequestTime = 0; // Timestamp of the last request in microseconds
    private static $requestIntervalMs = 10; // Minimum interval (in milliseconds) between requests
    private string $locale;
    private string $language;

    public function __construct($locale)
    {
        $this->locale = $locale;
        $this->language = substr($locale, 0, 2); // Extract the language code (e.g., 'fr', 'es')
    }

    public function getCachedData(string $ebirdId): ?array {
        global $wpdb;
        $tableName = Config::getTableSparqlCache();

        $cachedResult = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT result, expiration FROM $tableName WHERE ebird_id = %s",
                    $ebirdId
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

    public function fetchAndUpdateCachedData(string $ebirdId): array {
        $cachedData = $this->getCachedData($ebirdId);
        if ($cachedData && $cachedData['isFresh']) {
            return $cachedData['data'];
        }

        $currentTime = microtime(true) / 1000; // Current time in milliseconds
        $timeSinceLastRequest = $currentTime - self::$lastRequestTime;

        if ($timeSinceLastRequest < self::$requestIntervalMs) {
            usleep((self::$requestIntervalMs - $timeSinceLastRequest) * 1000); // Convert ms to microseconds
        }

        $sparqlUrl = "https://query.wikidata.org/sparql?query=" . urlencode($this->buildSparqlQuery($ebirdId));
        $sparqlHeaders = ["Accept: application/json"];
        $sparqlResponse = $this->executeCurl($sparqlUrl, $sparqlHeaders);
        $sparqlData = json_decode($sparqlResponse, true);

        self::$lastRequestTime = microtime(true) / 1000; // Update the last request time to the current time in milliseconds

        return $this->processAndCacheData($ebirdId, $sparqlData);
    }

    private function buildSparqlQuery(string $ebirdId): string {
        return <<<SPARQL
            SELECT ?item ?itemLabel ?itemDescription ?latinName ?image ?wikipedia WHERE {
                ?item wdt:P3444 "$ebirdId".
                    ?item wdt:P171*/wdt:P279* wd:Q5113.
                    OPTIONAL { ?item wdt:P225 ?latinName. }
                OPTIONAL { ?item wdt:P18 ?image. }
                OPTIONAL {
                    ?wikipedia schema:about ?item;
                    schema:isPartOf <https://fr.wikipedia.org/>.
                }
                SERVICE wikibase:label { bd:serviceParam wikibase:language "$this->language,en". }
            }
            LIMIT 1
            SPARQL;
    }

    private function processAndCacheData(string $ebirdId, array $sparqlData): array {
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
        $wpdb->replace(
                $tableName,
                [
                'ebird_id' => $ebirdId,
                'result' => json_encode($result),
                'expiration' => date('Y-m-d H:i:s', strtotime('+10 days')),
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
        // Ajouter l'en-tête User-Agent
        $defaultHeaders = [
            "User-Agent: wp-whobird/0.1 (https://github.com/evlist/wp-whobird ; vdv@dyomedea.com)"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

}
