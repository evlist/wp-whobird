<?php
// vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0

namespace WPWhoBird;

use WPWhoBird\Config;

require_once 'SparqlUtils.php';

class WikidataQuery
{
    private $locale;
    private $language;

    public function __construct($locale)
    {
        $this->locale = $locale;
        $this->language = substr($locale, 0, 2); // Extract the language code (e.g., 'fr', 'es')
        error_log('locale : ' . $this->locale);
    }

    /**
     * Fetch information about a bird (Aves class) entity based on a species name.
     *
     * @param string $ebirdId The common name of the species to query.
     * @return array|null Returns an array of entity data or null if no result is found.
     */
    public function fetchBirdEntity($ebirdId)
    {
        global $wpdb;

        // Table name for caching
        $tableName = Config::getTableSparqlCache();

        // Check if the species is already in the cache
        $cachedResult = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT result, expiration FROM $tableName WHERE ebird_id = %s",
                $ebirdId
            ),
            ARRAY_A
        );

        // If the result exists and it's not expired, return the cached data
        if ($cachedResult && strtotime($cachedResult['expiration']) > time()) {
            return json_decode($cachedResult['result'], true);
        }

        // Sanitize species name for SPARQL
        $ebirdId = sanitizeForSparql($ebirdId);

        // Prepare the SPARQL query to check if the entity belongs to the Aves class
        $query = <<<SPARQL
            SELECT ?item ?itemLabel ?itemDescription ?latinName ?image ?wikipedia WHERE {
                ?item wdt:P3444 "$ebirdId".  # eBird Id
                ?item wdt:P171*/wdt:P279* wd:Q5113.             # Check if the item belongs to Aves through the taxonomic hierarchy
                OPTIONAL { ?item wdt:P225 ?latinName. }           # Fetch Latin name (scientific name)
                OPTIONAL { ?item wdt:P18 ?image. }                # Fetch image (P18)
                OPTIONAL { 
                    ?wikipedia schema:about ?item;          # Fetch Wikipedia link
                               schema:isPartOf <https://fr.wikipedia.org/>. 
                }
                SERVICE wikibase:label { bd:serviceParam wikibase:language "$this->language,en". }
            }
            LIMIT 1
        SPARQL;
        error_log('query : ' . $query);

        $sparqlUrl = "https://query.wikidata.org/sparql?query=" . urlencode($query);
        $sparqlHeaders = ["Accept: application/json"];

        // Fetch the SPARQL response
        $sparqlResponse = $this->executeCurl($sparqlUrl, $sparqlHeaders);
        error_log('response : ' . print_r($sparqlResponse, true));
        $sparqlData = json_decode($sparqlResponse, true);

        // Extract the entity information
        $result = null;
        if (!empty($sparqlData['results']['bindings'])) {
            $binding = $sparqlData['results']['bindings'][0];
            $result = [
                'label' => $binding['itemLabel']['value'] ?? null,
                'description' => $binding['itemDescription']['value'] ?? null,
                'latinName' => $binding['latinName']['value'] ?? null,
                'image' => $binding['image']['value'] ?? null,
            ];

            // Cache the result in the database
            $expiration = date('Y-m-d H:i:s', strtotime('+10 day')); // Cache expires in 1 day
            $wpdb->replace(
                $tableName,
                [
                    'ebird_id' => $ebirdId,
                    'result' => json_encode($result),
                    'expiration' => $expiration,
                ],
                ['%s', '%s', '%s']
            );
        }

        // Return the result (null if no data is found)
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
            "User-Agent: wp-whobird/0.1 (https://github.com/evlist/wp-whobird ; vdv@dyomede.com)"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}
