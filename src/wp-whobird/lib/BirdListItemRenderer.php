<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 */
namespace WPWhoBird;

require_once 'WikidataQuery.php';
require_once 'ImageUtils.php';

class BirdListItemRenderer
{
    private string $ebirdId;

    public function __construct(string $birdnetId, ?string $locale = null)
    {
        // Convert birdnetId to ebirdId using TaxoCodeTableManager
        $this->ebirdId = getEbirdIdByBirdnetId((int) $birdnetId);
    }



    public function render(string $speciesName, string $recordingsUrls): string
    {

        $wikidataQuery = new WikidataQuery($locale ?? get_locale());
        $cache = $wikidataQuery->getCachedData($this->ebirdId);

        $needsRefresh = true;
        $birdData = [
            'commonName' => $speciesName
        ];
        if ($cache) {
            $needsRefresh = !$cache['isFresh'];
            $birdData = $cache['data'];
        }
        $dataEbirdId = $needsRefresh ? ' data-ebird-id="'.$this->ebirdId.'"' : '';
        return sprintf(
            '<li class="wpwbd-bird-entry" data-recordings="%s"%s>%s</li>',
            esc_attr($recordingsUrls),
            $dataEbirdId,
            $this->renderBirdData($birdData)
        );
    }

    public function renderBirdData(array $birdData): string
    {
        // Prepare additional information from Wikidata
        $description = $birdData['description'] ?? '';
        $latinName = $birdData['latinName'] ?? '';
        $commonName = $birdData['commonName'] ?? '???';
        $image = $birdData['image'] ?? plugins_url( '../assets/images/whoBIRD.svg', dirname(__FILE__) );
        // $image = $birdData['image'] ?? plugins_url( 'build/assets/images/whoBIRD.svg' );
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

        // Render the <li> element content with enriched structure
        return sprintf(
            '<div class="bird-thumbnail">
                %s
             </div>
             <div class="bird-info">
                <div class="common-name">%s</div>
                <div class="latin-name">%s</div>
             </div>
             <div class="bird-wikipedia-container">
                %s
             </div>',
            $thumbnailUrl ? sprintf('<img src="%s" alt="%s">', esc_url($thumbnailUrl), esc_attr($commonName)) : '',
            esc_html($commonName),
            esc_html($latinName),
            $wikipedia
        );
    }

}
