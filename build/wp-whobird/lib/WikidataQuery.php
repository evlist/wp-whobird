<?php
// vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0

namespace WPWhoBird;

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
     * @param string $speciesName The common name of the species to query.
     * @return array|null Returns an array of entity data or null if no result is found.
     */
    public function fetchBirdEntity($speciesName)
    {
        // Sanitize species name for SPARQL
        $speciesName = sanitizeForSparql($speciesName);

        // Prepare the SPARQL query to check if the entity belongs to the Aves class
        $query = <<<SPARQL
            SELECT ?item ?itemLabel ?itemDescription ?latinName ?image ?wikipedia WHERE {
                {
                    ?item rdfs:label "$speciesName"@$this->language.  # Match the common name in the specified language
                } UNION {
                    ?item wdt:P1448 "$speciesName"@$this->language.  # Alternative label
                } UNION {
                    ?item wdt:P1843 "$speciesName"@$this->language.  # Alternative label
                } UNION {
                    ?item skos:altLabel "$speciesName"@$this->language.  # Alternative label
                }  
                ?item wdt:P171*/wdt:P279* wd:Q5113.             # Check if the item belongs to Aves through the taxonomic hierarchy
                OPTIONAL { ?item wdt:P225 ?latinName. }           # Fetch Latin name (scientific name)
                OPTIONAL { ?item wdt:P18 ?image. }                # Fetch image (P18)
                OPTIONAL {                                                                                                             ?wikipedia schema:about ?item;          # Fetch Wikipedia link                                                                  schema:isPartOf <https://fr.wikipedia.org/>.                                                            }                                                                                                                 
                SERVICE wikibase:label { bd:serviceParam wikibase:language "$this->language,en". }
            }
    }
    LIMIT 1
    SPARQL;
    error_log('query : ' . $query);

    $sparqlUrl = "https://query.wikidata.org/sparql?query=" . urlencode($query);
    $sparqlHeaders = ["Accept: application/json"];

    // Fetch the SPARQL response
    $sparqlResponse = $this->executeCurl($sparqlUrl, $sparqlHeaders);
    error_log('response : ' . print_r($sparqlResponse, true)	);
    $sparqlData = json_decode($sparqlResponse, true);

    // Extract the entity information
    if (!empty($sparqlData['results']['bindings'])) {
        $result = $sparqlData['results']['bindings'][0];
        return [
            'label' => $result['itemLabel']['value'] ?? null,
            'description' => $result['itemDescription']['value'] ?? null,
            'latinName' => $result['latinName']['value'] ?? null,
            'image' => $result['image']['value'] ?? null,
        ];
    }

    // Return null if no results are found
    return null;
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
