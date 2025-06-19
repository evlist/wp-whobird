<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Entry point for rendering WhoBird observations.
 *
 * Loads the necessary renderer and related dependencies, then outputs observations
 * based on the provided $attributes context (typically called from a shortcode or block).
 *
 * Assumes this file is included in the context of a WordPress page or plugin logic.
 */

namespace WPWhoBird;

// Load required renderer and utility classes.
require_once __DIR__ . '/../../includes/WhoBirdRenderer.php';
require_once __DIR__ . '/../../includes/BirdListItemRenderer.php';
require_once __DIR__ . '/../../includes/WikidataQuery.php';

// Instantiate the main observations renderer.
$renderer = new WhoBirdRenderer();

// Output the rendered observations using the provided attributes.
echo $renderer->displayObservations($attributes);
