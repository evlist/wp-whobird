<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use PHPUnit\Framework\TestCase;
use WPWhoBird\BirdListItemRenderer;

require_once dirname( __DIR__ ) . '/includes/BirdListItemRenderer.php';

/**
 * Tests of the data-index* attributes on the bird entries (slice 001).
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

	public function test_qid_without_wikidata_data_has_no_label(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'Fringilla coelebs', 'wikidata_qid' => 'Q25471' );
		$tag = $this->liTag( $this->render() );
		$this->assertStringContainsString( ' data-index="wikidata:Q25471" data-index-kind="species"', $tag );
		$this->assertStringNotContainsString( 'data-index-label', $tag );
	}

	public function test_qid_with_wikidata_name_has_label(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'Fringilla coelebs', 'wikidata_qid' => 'Q25471' );
		$GLOBALS['wpwbd_test_cache']   = $this->freshCache( 'Pinson des arbres' );
		$tag = $this->liTag( $this->render() );
		$this->assertStringContainsString( 'data-index-label="Pinson des arbres"', $tag );
		$this->assertStringNotContainsString( 'data-birdnet-id', $tag, 'fresh cache needs no refresh' );
	}

	public function test_label_is_escaped(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'x', 'wikidata_qid' => 'Q1' );
		$GLOBALS['wpwbd_test_cache']   = $this->freshCache( 'Oiseau "rare" <b>' );
		$this->assertStringContainsString(
			'data-index-label="Oiseau &quot;rare&quot; &lt;b&gt;"',
			$this->liTag( $this->render() )
		);
	}

	public function test_stale_cache_keeps_refresh_attribute_and_label(): void {
		$GLOBALS['wpwbd_test_mapping'] = array( 'scientific_name' => 'x', 'wikidata_qid' => 'Q1' );
		$cache                         = $this->freshCache( 'Pinson des arbres' );
		$cache['isFresh']              = false;
		$GLOBALS['wpwbd_test_cache']   = $cache;
		$tag = $this->liTag( $this->render() );
		$this->assertStringContainsString( 'data-birdnet-id="42"', $tag );
		$this->assertStringContainsString( 'data-index-label="Pinson des arbres"', $tag );
	}
}
