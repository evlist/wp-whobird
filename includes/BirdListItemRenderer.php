<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * BirdListItemRenderer
 *
 * Responsible for rendering a single bird list item, enriched with data from Wikidata and BirdNET,
 * including names, thumbnail, and a Wikipedia link when available.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

namespace WPWhoBird;

require_once 'WikidataQuery.php';
require_once 'ImageUtils.php';

/**
 * Class BirdListItemRenderer
 *
 * Generates HTML for displaying a bird entry in the list, including
 * fetching cached Wikidata data, rendering the bird's names, thumbnail, and Wikipedia link.
 */
class BirdListItemRenderer
{
    /** @var int BirdNET integer ID */
    private int $birdnetId;

    /** @var string|null Locale/language code */
    private ?string $locale;

    /**
     * BirdListItemRenderer constructor.
     *
     * @param int $birdnetId BirdNET integer ID
     * @param string|null $locale Optional locale/language code (defaults to current site locale)
     */
    public function __construct(int $birdnetId, ?string $locale = null)
    {
        $this->birdnetId = $birdnetId;
        $this->locale = $locale ?? get_locale();
    }

    /**
     * Render the full <li> element for a bird, including HTML attributes and content.
     *
     * @param string $speciesName      The species' common name (used as fallback if no Wikidata)
     * @param string $recordingsUrls   Comma-separated URLs for audio recordings
     * @return string                  HTML <li> element as a string
     */
    public function render(string $speciesName, string $recordingsUrls): string
    {
        // Fetch cached Wikidata data for this BirdNET ID
        $wikidataQuery = new WikidataQuery($this->locale);
        $cache = $wikidataQuery->getCachedData($this->birdnetId);

        // Prepare initial/default bird data
        $needsRefresh = true;
        $birdData = [
            'commonName' => $speciesName
        ];

        // Use cached data if available
        if ($cache) {
            $needsRefresh = !$cache['isFresh'];
            if ($cache['data']) {
                $birdData = $cache['data'];
            }
        }

        // Add data-birdnet-id attribute if data is not fresh (for AJAX refresh)
        $dataBirdnetId = $needsRefresh ? ' data-birdnet-id="' . $this->birdnetId . '"' : '';

        // Render the <li> element with all details
        return sprintf(
            '<li class="wpwbd-bird-entry" data-recordings="%s"%s>%s</li>',
            esc_attr($recordingsUrls),
            $dataBirdnetId,
            $this->renderBirdData($birdData)
        );
    }

    /**
     * Render the inner contents of a bird entry (names, thumbnail, Wikipedia link).
     *
     * @param array $birdData Data array with keys like commonName, latinName, image, wikipedia
     * @return string         HTML content for inside the <li>
     */
    public function renderBirdData(array $birdData): string
    {
        // Prepare display fields from Wikidata or defaults
        $description = $birdData['description'] ?? '';
        $latinName = $birdData['latinName'] ?? '';
        $commonName = $birdData['commonName'] ?? '???';
        $image = $birdData['image'] ?? plugins_url('resources/images/whoBIRD.svg', dirname(__FILE__));
        $wikipedia = '';

        // If a Wikipedia URL is present, create a link with icons
        if (!empty($birdData['wikipedia'])) {
            $wikipedia .= '<a href="' . esc_url($birdData['wikipedia']) . '" target="_blank" rel="noopener noreferrer" class="bird-wikipedia-link">';
            $wikipedia .= '<i class="fab fa-wikipedia-w normal"></i>'; // FontAwesome Wikipedia logo
            $wikipedia .= '<i class="fas fa-up-right-from-square exponent"></i>'; // FontAwesome "external link" icon
            $wikipedia .= '</a>';
        }

        // Generate a 200px-wide thumbnail from the image URL
        $thumbnailUrl = $image ? getThumbnailUrl($image, '200px') : '';

        // Compose the HTML for the entry
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

