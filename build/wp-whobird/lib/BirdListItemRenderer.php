<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 */
namespace WPWhoBird;

class BirdListItemRenderer
{
    private string $speciesName;
    private string $recordingsUrls;

    public function __construct(string $speciesName, string $recordingsUrls)
    {
        $this->speciesName = $speciesName;
        $this->recordingsUrls = $recordingsUrls;
    }

    public function render(): string
    {
        return sprintf(
            '<li data-recordings="%s">%s</li>',
            esc_attr($this->recordingsUrls),
            esc_html($this->speciesName)
        );
    }
}
