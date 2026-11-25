<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use PHPUnit\Framework\TestCase;
use WPWhoBird\Config;
use WPWhoBird\WhoBirdRenderer;

require_once dirname( __DIR__ ) . '/includes/config.php';
require_once dirname( __DIR__ ) . '/includes/BirdListItemRenderer.php';
require_once dirname( __DIR__ ) . '/includes/WhoBirdRenderer.php';
require_once dirname( __DIR__ ) . '/includes/admin-settings.php';

/**
 * Tests of slice 005: registered settings are the ones read, and paths stay inside uploads.
 */
class SettingsTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/wpwbd-test-' . uniqid();
		mkdir( $this->root . '/uploads/WhoBird/databases', 0777, true );
		mkdir( $this->root . '/uploads/WhoBird/recordings', 0777, true );
		mkdir( $this->root . '/outside', 0777, true );

		$GLOBALS['wpwbd_test_uploads']        = array( 'basedir' => $this->root . '/uploads', 'baseurl' => 'http://example.test/uploads' );
		$GLOBALS['wpwbd_test_options']        = array();
		$GLOBALS['wpwbd_test_settings_error'] = array();
		$GLOBALS['wpwbd_test_cache']          = false;
		$GLOBALS['wpwbd_test_mapping']        = null;
		$GLOBALS['wpwbd_test_post_date']      = '2026-01-10';
	}

	protected function tearDown(): void {
		exec( 'rm -rf ' . escapeshellarg( $this->root ) );
	}

	// ---- Path filter ---------------------------------------------------------------------

	public function pathProvider(): array {
		return array(
			'plain'              => array( 'WhoBird/recordings', 'WhoBird/recordings' ),
			'normalised'         => array( ' a//b/./c/ ', 'a/b/c' ),
			'backslashes'        => array( 'a\\b', 'a/b' ),
			'parent'             => array( '../x', '' ),
			'parent inside'      => array( 'a/../../x', '' ),
			'parent backslash'   => array( 'a\\..\\..\\x', '' ),
			'absolute'           => array( '/etc/passwd', '' ),
			'drive'              => array( 'C:\\Windows', '' ),
			'scheme'             => array( 'phar://x.phar/a', '' ),
			'empty'              => array( '', '' ),
			'dot only'           => array( '.', '' ),
			'nul'                => array( "a\0b", '' ),
			'not a string'       => array( array( 'a' ), '' ),
			'dots in names'      => array( 'a..b/c.d', 'a..b/c.d' ),
		);
	}

	/** @dataProvider pathProvider */
	public function test_path_filter( $input, string $expected ): void {
		$this->assertSame( $expected, Config::sanitizeRelativePath( $input ) );
	}

	// ---- The registered options are the ones read -----------------------------------------------

	public function test_defaults_when_nothing_is_saved(): void {
		$this->assertSame( 'WhoBird/recordings', Config::getRecordingsPath() );
		$this->assertSame( 'WhoBird/databases/BirdDatabase.db', Config::getDatabasePath() );
		$this->assertSame( 0.7, Config::getThreshold() );
		$this->assertTrue( Config::shouldGenerateTextWhenNoObservations() );
	}

	public function test_saved_values_are_read_under_the_registered_names(): void {
		$GLOBALS['wpwbd_test_options'] = array(
			'wpwhobird_recordings_path' => 'rec/audio',
			'wpwhobird_database_path'   => 'db/my.db',
			'wpwhobird_threshold'       => '0.35',
			'wpwhobird_should_generate_text_when_no_observations' => '',
		);
		$this->assertSame( 'rec/audio', Config::getRecordingsPath() );
		$this->assertSame( 'db/my.db', Config::getDatabasePath() );
		$this->assertSame( 0.35, Config::getThreshold() );
		$this->assertFalse( Config::shouldGenerateTextWhenNoObservations() );
	}

	public function test_the_old_option_names_are_not_read(): void {
		$GLOBALS['wpwbd_test_options'] = array(
			'wp-whobird_recordings_path' => 'old/rec',
			'wp-whobird_database_path'   => 'old/db.db',
			'wp-whobird_threshold'       => 0.1,
		);
		$this->assertSame( 'WhoBird/recordings', Config::getRecordingsPath() );
		$this->assertSame( 'WhoBird/databases/BirdDatabase.db', Config::getDatabasePath() );
		$this->assertSame( 0.7, Config::getThreshold() );
	}

	public function test_unacceptable_stored_values_fall_back_to_the_defaults(): void {
		$GLOBALS['wpwbd_test_options'] = array(
			'wpwhobird_recordings_path' => '../../etc',
			'wpwhobird_database_path'   => '/etc/passwd',
			'wpwhobird_threshold'       => '7',
		);
		$this->assertSame( 'WhoBird/recordings', Config::getRecordingsPath() );
		$this->assertSame( 'WhoBird/databases/BirdDatabase.db', Config::getDatabasePath() );
		$this->assertSame( 0.7, Config::getThreshold() );
	}

	// ---- Settings page --------------------------------------------------------------------

	private function registerSettings(): void {
		$GLOBALS['wpwbd_test_registered'] = array();
		( new \WPWhoBird\WhoBirdAdminSettings() )->registerSettings();
	}

	public function test_registered_options_and_defaults_match_what_is_read(): void {
		$this->registerSettings();
		$registered = $GLOBALS['wpwbd_test_registered'];
		$this->assertSame( 'WhoBird/recordings', $registered['wpwhobird_recordings_path']['default'] );
		$this->assertSame( 'WhoBird/databases/BirdDatabase.db', $registered['wpwhobird_database_path']['default'] );
		$this->assertSame( 0.7, $registered['wpwhobird_threshold']['default'] );
		$this->assertTrue( $registered['wpwhobird_should_generate_text_when_no_observations']['default'] );
	}

	public function test_saving_a_path_outside_uploads_is_refused_and_keeps_the_previous_value(): void {
		$this->registerSettings();
		$GLOBALS['wpwbd_test_options']['wpwhobird_recordings_path'] = 'my/rec';
		$callback = $GLOBALS['wpwbd_test_registered']['wpwhobird_recordings_path']['sanitize_callback'];

		$this->assertSame( 'a/b', $callback( 'a//b/' ) );
		$this->assertSame( array(), $GLOBALS['wpwbd_test_settings_error'] );

		$this->assertSame( 'my/rec', $callback( '../../../etc' ) );
		$this->assertSame( 'wpwhobird_recordings_path', $GLOBALS['wpwbd_test_settings_error'][0][0] );
	}

	public function test_saving_an_invalid_database_path_without_previous_value_gives_the_default(): void {
		$this->registerSettings();
		$callback = $GLOBALS['wpwbd_test_registered']['wpwhobird_database_path']['sanitize_callback'];
		$this->assertSame( 'WhoBird/databases/BirdDatabase.db', $callback( '/abs/path.db' ) );
	}

	public function test_saving_a_threshold_out_of_range_gives_the_default(): void {
		$this->registerSettings();
		$callback = $GLOBALS['wpwbd_test_registered']['wpwhobird_threshold']['sanitize_callback'];
		$this->assertSame( 0.7, $callback( '2' ) );
		$this->assertSame( 0.25, $callback( '0.25' ) );
	}

	// ---- The renderer uses them (real SQLite file, real files) ---------------------------------------

	private function makeDatabase( string $relative ): string {
		$path = $this->root . '/uploads/' . $relative;
		@mkdir( dirname( $path ), 0777, true );
		$db = new SQLite3( $path );
		$db->exec( 'CREATE TABLE BirdObservations (BirdNET_ID INTEGER, SpeciesName TEXT, TimeInMillis INTEGER, Probability REAL)' );
		$t = strtotime( '2026-01-10 12:00:00' ) * 1000;
		$db->exec( "INSERT INTO BirdObservations VALUES (1, 'weak bird', $t, 0.4)" );
		$db->exec( "INSERT INTO BirdObservations VALUES (2, 'strong bird', $t, 0.8)" );
		$db->close();
		return (string) $t;
	}

	private function render(): string {
		return ( new WhoBirdRenderer() )->displayObservations( array( 'periodNumber' => 1, 'periodUnit' => 'day' ) );
	}

	public function test_threshold_setting_filters_the_observations(): void {
		$this->makeDatabase( 'WhoBird/databases/BirdDatabase.db' );

		$html = $this->render(); // Default 0.7.
		$this->assertStringContainsString( 'strong bird', $html );
		$this->assertStringNotContainsString( 'weak bird', $html );

		$GLOBALS['wpwbd_test_options']['wpwhobird_threshold'] = '0.3';
		$html = $this->render();
		$this->assertStringContainsString( 'weak bird', $html );
		$this->assertStringContainsString( 'strong bird', $html );

		$GLOBALS['wpwbd_test_options']                         = array( 'wp-whobird_threshold' => 0.1 );
		$this->assertStringNotContainsString( 'weak bird', $this->render(), 'the old option name is ignored' );
	}

	public function test_database_path_setting_is_used(): void {
		$this->makeDatabase( 'other/place.db' );
		$GLOBALS['wpwbd_test_options']['wpwhobird_database_path'] = 'other/place.db';
		$this->assertStringContainsString( 'strong bird', $this->render() );
	}

	public function test_a_database_path_that_leaves_uploads_is_not_followed(): void {
		$this->makeDatabase( 'WhoBird/databases/BirdDatabase.db' );
		// A different database outside uploads, reachable with "..": must not be used.
		$outside = new SQLite3( $this->root . '/outside/evil.db' );
		$outside->exec( 'CREATE TABLE BirdObservations (BirdNET_ID INTEGER, SpeciesName TEXT, TimeInMillis INTEGER, Probability REAL)' );
		$t = strtotime( '2026-01-10 12:00:00' ) * 1000;
		$outside->exec( "INSERT INTO BirdObservations VALUES (9, 'evil bird', $t, 0.99)" );
		$outside->close();

		$GLOBALS['wpwbd_test_options']['wpwhobird_database_path'] = '../outside/evil.db';
		$html = $this->render();
		$this->assertStringNotContainsString( 'evil bird', $html );
		$this->assertStringContainsString( 'strong bird', $html, 'the default database is used instead' );
	}

	public function test_a_database_symlinked_outside_uploads_is_refused(): void {
		$this->makeDatabase( 'WhoBird/databases/BirdDatabase.db' );
		rename( $this->root . '/uploads/WhoBird/databases/BirdDatabase.db', $this->root . '/outside/real.db' );
		symlink( $this->root . '/outside/real.db', $this->root . '/uploads/WhoBird/databases/BirdDatabase.db' );
		$this->expectException( \Exception::class );
		$this->render();
	}

	public function test_recordings_are_found_in_the_configured_directory_only(): void {
		$id = $this->makeDatabase( 'WhoBird/databases/BirdDatabase.db' );
		mkdir( $this->root . '/uploads/rec' );
		touch( $this->root . '/uploads/rec/' . $id . '.wav' );

		$this->assertStringNotContainsString( '.wav', $this->render(), 'default directory has no recording' );

		$GLOBALS['wpwbd_test_options']['wpwhobird_recordings_path'] = 'rec';
		$this->assertStringContainsString( 'data-recordings="http://example.test/uploads/rec/' . $id . '.wav"', $this->render() );
	}

	public function test_a_recordings_path_that_leaves_uploads_is_not_followed(): void {
		$id = $this->makeDatabase( 'WhoBird/databases/BirdDatabase.db' );
		touch( $this->root . '/outside/' . $id . '.wav' );
		$GLOBALS['wpwbd_test_options']['wpwhobird_recordings_path'] = '../outside';
		$this->assertStringNotContainsString( '.wav', $this->render() );
	}

	public function test_a_recording_symlinked_outside_uploads_is_ignored(): void {
		$id = $this->makeDatabase( 'WhoBird/databases/BirdDatabase.db' );
		touch( $this->root . '/outside/secret.wav' );
		symlink( $this->root . '/outside/secret.wav', $this->root . '/uploads/WhoBird/recordings/' . $id . '.wav' );
		$this->assertStringNotContainsString( '.wav', $this->render() );
	}
}
