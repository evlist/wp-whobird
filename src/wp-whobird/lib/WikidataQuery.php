<?php
// vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0

namespace WPWhoBird;

use WPWhoBird\Config;

require_once 'SparqlUtils.php';
require_once 'ImageUtils.php';

class WikidataQuery {
    private static $lastRequestTime = 0; // Timestamp of the last request
    private static $requestInterval = 1; // Minimum interval (in seconds) between requests

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
        $cachedData = getCachedData($ebirdId);
        if ($cachedData && $cache['isFresh']) {
            return $cachedData['data'];
        }

        $timeSinceLastRequest = time() - self::$lastRequestTime;

        if ($timeSinceLastRequest < self::$requestInterval) {
            sleep(self::$requestInterval - $timeSinceLastRequest);
        }

        self::$lastRequestTime = time();

        $sparqlUrl = "https://query.wikidata.org/sparql?query=" . urlencode($this->buildSparqlQuery($ebirdId));
        $sparqlHeaders = ["Accept: application/json"];
        $sparqlResponse = $this->executeCurl($sparqlUrl, $sparqlHeaders);
        $sparqlData = json_decode($sparqlResponse, true);

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
                'label' => $binding['itemLabel']['value'] ?? null,
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
}
