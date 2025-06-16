<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 */
namespace WPWhoBird;

require_once 'WikidataQuery.php';
require_once 'ImageUtils.php';

class BirdListItemRenderer
{
    private int $birdnetId;
    private ?string $locale;

    public function __construct(int $birdnetId, ?string $locale = null)
    {
        $this->birdnetId = $birdnetId;
        $this->locale = $locale ?? get_locale();
    }

    public function render(string $speciesName, string $recordingsUrls): string
    {
        $wikidataQuery = new WikidataQuery($this->locale);
        // Use birdnet_id for cache and queries
        $cache = $wikidataQuery->getCachedData($this->birdnetId);

        $needsRefresh = true;
        $birdData = [
            'commonName' => $speciesName
        ];
        if ($cache) {
            $needsRefresh = !$cache['isFresh'];
            if ($cache['data']) {
                $birdData = $cache['data'];
            }
        }
        $dataBirdnetId = $needsRefresh ? ' data-birdnet-id="' . $this->birdnetId . '"' : '';
        return sprintf(
            '<li class="wpwbd-bird-entry" data-recordings="%s"%s>%s</li>',
            esc_attr($recordingsUrls),
            $dataBirdnetId,
            $this->renderBirdData($birdData)
        );
    }

    public function renderBirdData(array $birdData): string
    {
        // Prepare additional information from Wikidata
        $description = $birdData['description'] ?? '';
        $latinName = $birdData['latinName'] ?? '';
        $commonName = $birdData['commonName'] ?? '???';
        $image = $birdData['image'] ?? plugins_url('resources/images/whoBIRD.svg', dirname(__FILE__));
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
