<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
*/
namespace WPWhoBird;

require_once 'WikidataQuery.php';

class BirdListItemRenderer
{
    private string $speciesName;
    private string $recordingsUrls;
    private string $ebirdId;
    private string $birdnetId;
    private WikidataQuery $wikidataQuery;

    public function __construct(string $speciesName, string $birdnetId, string $recordingsUrls, ?WikidataQuery $wikidataQuery = null, ?string $locale = null)
    {
        $this->speciesName = $speciesName;
        $this->birdnetId = $birdnetId;
        $this->recordingsUrls = $recordingsUrls;
                
        // Convert birdnetId to ebirdId using TaxoCodeTableManager
        $this->ebirdId = getEbirdIdByBirdnetId((int) $birdnetId);


        // Use the provided WikidataQuery or create one internally
        $this->wikidataQuery = $wikidataQuery ?? new WikidataQuery($locale ?? get_locale());
    }

    public function render(): string
    {
        // Fetch bird information from Wikidata
        $birdData = $this->wikidataQuery->fetchBirdEntity($this->ebirdId);

        // Prepare additional information from Wikidata
        $description = $birdData['description'] ?? 'No description available';
        $latinName = $birdData['latinName'] ?? 'Latin name not found';
        $image = $birdData['image'] ?? '';

        // Render the <li> element with additional data
        return sprintf(
            '<li data-recordings="%s">
                <strong>%s</strong><br>
                <em>%s</em><br>
                %s
                <p>%s</p>
            </li>',
            esc_attr($this->recordingsUrls),
            esc_html($this->speciesName),
            esc_html($latinName),
            $image ? sprintf('<img src="%s" alt="%s" style="max-width:150px;">', esc_url($image), esc_attr($this->speciesName)) : '',
            esc_html($description)
        );
    }
}
