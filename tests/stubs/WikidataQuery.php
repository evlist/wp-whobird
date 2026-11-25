<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace WPWhoBird;

/**
 * Test stand-in for the Wikidata cache access (shadows includes/WikidataQuery.php).
 */
class WikidataQuery {
	public function __construct( $language ) {}

	public function getCachedData( $birdnetId ) {
		return $GLOBALS['wpwbd_test_cache'];
	}
}
