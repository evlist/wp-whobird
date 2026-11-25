<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace WPWhoBird;

/**
 * Test stand-in for the Wikidata cache and fetch (shadows includes/WikidataQuery.php).
 *
 * Globals set by the tests: wpwbd_test_cache (answer of getCachedData),
 * wpwbd_test_fetch_result (answer of fetchAndUpdateCachedData).
 * Globals filled for the tests: wpwbd_test_wq_locales, wpwbd_test_fetch_calls.
 */
class WikidataQuery {
	public function __construct( $language ) {
		$GLOBALS['wpwbd_test_wq_locales'][] = $language;
	}

	public function getCachedData( $birdnetId ) {
		return $GLOBALS['wpwbd_test_cache'];
	}

	public function fetchAndUpdateCachedData( $birdnetId, $wikidataId ) {
		$GLOBALS['wpwbd_test_fetch_calls'][] = array( $birdnetId, $wikidataId );
		return $GLOBALS['wpwbd_test_fetch_result'];
	}
}
