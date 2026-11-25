<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use PHPUnit\Framework\TestCase;
use WPWhoBird\BirdListItemRenderer;

require_once dirname( __DIR__ ) . '/includes/BirdListItemRenderer.php';

/**
 * Tests of the data-index mark on the bird entries (slices 001 and 002).
 */
class BirdListItemRendererTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpwbd_test_mapping'] = null;
		$GLOBALS['wpwbd_test_cache']   = false;
	}

	private function render(): string {
		return ( new BirdListItemRenderer( 42, 'fr_FR' ) )->render( 'pinson des arbres', 'a.mp3' );
	}

	private function liTag( string $html ): string {
		preg_match( '/^<li[^>]*>/', $html, $m );
		return $m[0];
	}

	private function freshCache( string $name ): array {
		return array(
			'isFresh' => true,
			'data'    => array(
				'commonName' => $name,
				'latinName'  => 'Fringilla coelebs',
			),
		);
	}

	public function test_no_mapping_gives_unchanged_output(): void {
		$tag = $this->liTag( $this->render() );
		$this->assertSame( '<li class="wpwbd-bird-entry" data-recordings="a.mp3" data-birdnet-id="42">', $tag );
	}

	public function test_empty_qid_gives_no_index_attributes(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'Fringilla coelebs', 'wikidata_qid' => null );
		$this->assertStringNotContainsString( 'data-index', $this->render() );
	}

	public function test_malformed_qid_is_ignored(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'x', 'wikidata_qid' => 'Q12"><script>' );
		$this->assertStringNotContainsString( 'data-index', $this->render() );
	}

	/**
	 * Decode the JSON mark from the data-index attribute of the <li> tag.
	 */
	private function mark( string $tag ): array {
		$this->assertSame( 1, preg_match( '/ data-index="([^"]*)"/', $tag, $m ), 'data-index present' );
		$mark = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
		$this->assertIsArray( $mark );
		return $mark;
	}

	public function test_qid_without_wikidata_data_has_no_label(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'Fringilla coelebs', 'wikidata_qid' => 'Q25471' );
		$mark = $this->mark( $this->liTag( $this->render() ) );
		$this->assertSame(
			array(
				'topic'  => 'wikidata:Q25471',
				'kind'   => 'species',
				'source' => 'whobird',
			),
			$mark
		);
	}

	public function test_qid_with_wikidata_name_has_label(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'Fringilla coelebs', 'wikidata_qid' => 'Q25471' );
		$GLOBALS['wpwbd_test_cache']   = $this->freshCache( 'Pinson des arbres' );
		$tag = $this->liTag( $this->render() );
		$this->assertSame( 'Pinson des arbres', $this->mark( $tag )['label'] );
		$this->assertStringNotContainsString( 'data-birdnet-id', $tag, 'fresh cache needs no refresh' );
	}

	public function test_old_companion_attributes_are_gone(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'x', 'wikidata_qid' => 'Q1' );
		$GLOBALS['wpwbd_test_cache']   = $this->freshCache( 'Pinson des arbres' );
		$tag = $this->liTag( $this->render() );
		$this->assertStringNotContainsString( 'data-index-kind', $tag );
		$this->assertStringNotContainsString( 'data-index-label', $tag );
		$this->assertSame( 1, substr_count( $tag, 'data-index=' ) );
	}

	public function test_accents_are_not_escaped_as_unicode_sequences(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'x', 'wikidata_qid' => 'Q1' );
		$GLOBALS['wpwbd_test_cache']   = $this->freshCache( 'Pic épeiche' );
		$tag = $this->liTag( $this->render() );
		$this->assertStringContainsString( 'Pic épeiche', $tag );
		$this->assertStringNotContainsString( '\\u00', $tag );
	}

	public function test_label_with_quotes_and_apostrophe_round_trips(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'x', 'wikidata_qid' => 'Q1' );
		$name                          = 'Oiseau "rare" <b> de l\'île';
		$GLOBALS['wpwbd_test_cache']   = $this->freshCache( $name );
		$tag = $this->liTag( $this->render() );
		$this->assertStringNotContainsString( '<b>', $tag );
		$this->assertSame( $name, $this->mark( $tag )['label'] );
	}

	public function test_stale_cache_keeps_refresh_attribute_and_label(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'x', 'wikidata_qid' => 'Q1' );
		$cache                         = $this->freshCache( 'Pinson des arbres' );
		$cache['isFresh']              = false;
		$GLOBALS['wpwbd_test_cache']   = $cache;
		$tag = $this->liTag( $this->render() );
		$this->assertStringContainsString( 'data-birdnet-id="42"', $tag );
		$this->assertSame( 'Pinson des arbres', $this->mark( $tag )['label'] );
	}
}
