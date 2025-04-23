<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 */
namespace WPWhoBird;

require_once __DIR__ . '/lib/WhoBirdRenderer.php';

// Initialize and render the observations
$renderer = new WhoBirdRenderer();
$renderer->displayObservations();
