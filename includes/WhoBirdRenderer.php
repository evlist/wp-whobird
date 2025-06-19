<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Provides methods for rendering bird observation data for the WPWhoBird plugin.
 *
 * Handles database access, filtering, and formatting of bird observation results,
 * as well as rendering playback controls and outputting HTML markup for display.
 * Integrates with WordPress options and configuration for dynamic settings.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 *
 */
namespace WPWhoBird;

use DateTime;
use SQLite3;
use WPWhoBird\Config;

class WhoBirdRenderer
{
    /**
     * @var string Path to recordings directory relative to uploads base directory.
     */
    private string $recordingsPath;

    /**
     * @var string Path to SQLite database relative to uploads base directory.
     */
    private string $databasePath;

    /**
     * Initializes the renderer with WordPress-configured paths.
     *
     * Retrieves the recordings and database paths from WordPress options,
     * falling back to defaults if not set.
     */
    public function __construct()
    {
        // Retrieve the dynamic values from the WordPress options
        $this->recordingsPath = get_option('wp-whobird_recordings_path', 'WhoBird/recordings');
        $this->databasePath = get_option('wp-whobird_database_path', 'WhoBird/databases/BirdDatabase.db');
    }

    /**
     * Get a read-only SQLite3 database connection for bird observations.
     *
     * @return SQLite3
     * @throws \Exception if database cannot be opened.
     */
    private function getDatabaseConnection(): SQLite3
    {
        $dbPath = wp_get_upload_dir()['basedir'] . '/' . $this->databasePath;

        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        if (!$db) {
            throw new \Exception(__('Failed to open database at', 'wp-whobird') . ' ' . $dbPath);
        }

        return $db;
    }

    /**
     * Resolve and return a comma-separated list of URLs for valid recordings.
     *
     * @param string $recordingIdsString Comma-separated string of recording IDs.
     * @return string Comma-separated URLs of the recordings.
     */
    private function getRecordingsUrls(string $recordingIdsString): string
    {
        $recordingIds = explode(',', $recordingIdsString);
        $recordingUrls = [];
        $uploadDir = wp_get_upload_dir();

        $previousRecordingId = 0;
        foreach ($recordingIds as $recordingId) {
            if ($recordingId - $previousRecordingId >= 3000) {
                $previousRecordingId = $recordingId;
                $recordingPath = $uploadDir['basedir'] . '/' . $this->recordingsPath . '/' . $recordingId . '.wav';
                if (is_file($recordingPath) && is_readable($recordingPath)) {
                    $recordingUrl = $uploadDir['baseurl'] . '/' . $this->recordingsPath . '/' . $recordingId . '.wav';
                    $recordingUrls[] = $recordingUrl;
                }}
        }

        return implode(',', $recordingUrls);
    }

    /**
     * Render the HTML for the bird audio player controls.
     *
     * @return string HTML markup for the audio player.
     */
    private function playerDisplay(): string
    {
        return '<div id="audio-player-container">
            <div id="audio-player">
            <div class="player">
            <button id="prev" class="control-button" title="' . esc_attr__('Previous', 'wp-whobird') . '">⏮</button>
            <button id="play-pause" class="control-button" title="' . esc_attr__('Play/Pause', 'wp-whobird') . '">▶️</button>
            <button id="next" class="control-button" title="' . esc_attr__('Next', 'wp-whobird') . '">⏭</button>
            </div>
            <div class="track-info">
            <span id="current-track">' . esc_html__('Track', 'wp-whobird') . '</span>
            </div>
            </div>
            </div>';
    }

    /**
     * Query and render a list of bird observations within a given time range.
     *
     * @param int $startTime Start timestamp (milliseconds).
     * @param int $endTime End timestamp (milliseconds).
     * @return string Rendered HTML list of bird observations.
     */
    private function getObservationsList($startTime, $endTime): string
    {
        $db = $this->getDatabaseConnection();

        // Get the threshold value from the admin settings
        $threshold = floatval(get_option('wp-whobird_threshold', 0.7));

        $query = $db->prepare(
                "SELECT BirdNET_ID, SpeciesName, group_concat(TimeInMillis) as timestamps 
                FROM BirdObservations 
                WHERE TimeInMillis >= :startTime AND TimeInMillis < :endTime AND Probability >= :threshold 
                GROUP BY BirdNET_ID, SpeciesName"
                );

        $query->bindValue(':startTime', $startTime, SQLITE3_INTEGER);
        $query->bindValue(':endTime', $endTime, SQLITE3_INTEGER);
        $query->bindValue(':threshold', $threshold, SQLITE3_FLOAT);

        $results = $query->execute();

        $list = '';
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $listItemRenderer = new BirdListItemRenderer(
                    (int)$row['BirdNET_ID'],
                    );
            $list .= $listItemRenderer->render(
                    $row['SpeciesName'],
                    $this->getRecordingsUrls($row['timestamps'])
                    );
        }

        return $list;
    }

    /**
     * Display bird observations for a given period.
     *
     * Calculates the start and end timestamps based on attributes,
     * queries for matching observations, and returns the formatted HTML
     * for display, including a player if observations exist.
     *
     * @param array $attributes Attributes from the shortcode or block.
     * @return string HTML markup for observations or a message if none.
     */
    public function displayObservations($attributes): string
    {
        // error_log('attributes: ' . print_r($attributes, true));
        $periodNumber = isset($attributes['periodNumber']) ? intval($attributes['periodNumber']) : 1;
        $periodUnit = isset($attributes['periodUnit']) ? $attributes['periodUnit'] : 'day';
        $isoDate = get_the_date('Y-m-d');
        $date = new DateTime($isoDate);
        $date->modify('+1 day');
        // error_log('end: '.$date->format('Y-m-d'));
        $endTime = $date->getTimestamp() * 1000;
        $date->modify("-{$periodNumber} {$periodUnit}");
        // error_log('start: '.$date->format('Y-m-d'));
        $startTime = $date->getTimestamp() * 1000;
        $list = $this->getObservationsList($startTime, $endTime);

        if (empty($list)) {
            if (Config::shouldGenerateTextWhenNoObservations()) {
                return '<div class="wpwbd_observations wpwbd_empty_observations">' .
                    '<p>' . __('We did not identify any birds with the whoBIRD application.', 'wp-whobird') . '</p>' .
                    '</div>';
            } else {
                return '';
            }
        } else {
            return '<div class="wpwbd_observations">' .
                '<p>' . __('We heard and identified the following birds with the whoBIRD application:', 'wp-whobird') . '</p>' .
                '<ul class="wpwbd_list">' .
                $list .
                '</ul>' .
                $this->playerDisplay() .
                '</div>';
        }
    }
}
