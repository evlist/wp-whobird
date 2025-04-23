<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 */
namespace WPWhoBird;

require_once __DIR__ . '/lib/WhoBirdRenderer.php';
require_once __DIR__ . '/lib/BirdListItemRenderer.php';
require_once __DIR__ . '/lib/WikidataQuery.php';

$renderer = new WhoBirdRenderer();
echo $renderer->displayObservations();
