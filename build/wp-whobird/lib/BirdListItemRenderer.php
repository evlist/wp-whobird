<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 */
namespace WPWhoBird;

require_once 'WikidataQuery.php';
require_once 'ImageUtils.php';

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
        $wikipedia = '';

        // Check if the Wikipedia URL is set
        if (!empty($birdData['wikipedia'])) {
            $wikipedia .= '<a href="' . esc_url($birdData['wikipedia']) . '" target="_blank" rel="noopener noreferrer" class="bird-wikipedia-link">';
            $wikipedia .= '<i class="fab fa-wikipedia-w normal"></i>'; // FontAwesome Wikipedia logo
            $wikipedia .= '<i class="fas fa-up-right-from-square exponent"></i>'; // FontAwesome "external link" icon
            $wikipedia .= '</a>';
        }

        // Transform the image URL to fetch the thumbnail (200px wide)
        $thumbnailUrl = $image ? getThumbnailUrl($image, '200px') : '';

        // Render the <li> element with enriched structure
        return sprintf(
            '<li class="wpwbd-bird-entry" data-recordings="%s">
                <div class="bird-thumbnail">
                    %s
                </div>
                <div class="bird-info">
                    <div class="common-name">%s</div>
                    <div class="latin-name">%s</div>
                </div>
                <div class="bird-wikipedia-container">
                    %s
                </div>
            </li>',
            esc_attr($this->recordingsUrls),
            $thumbnailUrl ? sprintf('<img src="%s" alt="%s">', esc_url($thumbnailUrl), esc_attr($this->speciesName)) : '',
            esc_html($this->speciesName),
            esc_html($latinName),
            $wikipedia
        );
    }
}
