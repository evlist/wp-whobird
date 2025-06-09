<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 */
namespace WPWhoBird;

require_once __DIR__ . '/lib/WhoBirdRenderer.php';
require_once __DIR__ . '/lib/BirdListItemRenderer.php';
require_once __DIR__ . '/lib/WikidataQuery.php';

// Use $startDate for querying observations
$renderer = new WhoBirdRenderer();
echo $renderer->displayObservations($attributes);
