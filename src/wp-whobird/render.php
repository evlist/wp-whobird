<?php
/**
 */
namespace WPWhoBird;

require_once __DIR__ . '/lib/WhoBirdRenderer.php';
require_once __DIR__ . '/lib/BirdListItemRenderer.php';
require_once __DIR__ . '/lib/WikidataQuery.php';

// Use $startDate for querying observations
$renderer = new WhoBirdRenderer();
echo $renderer->displayObservations($attributes);
