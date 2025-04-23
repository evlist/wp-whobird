<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 */
namespace WPWhoBird;

use DateTime;
use SQLite3;

class WhoBirdRenderer
{
    private string $recordingsPath;
    private string $databasePath;

    public function __construct()
    {
        // Retrieve the dynamic values from the WordPress options
        $this->recordingsPath = get_option('wp-whobird_recordings_path', 'WhoBird/recordings');
        $this->databasePath = get_option('wp-whobird_database_path', 'WhoBird/databases/BirdDatabase.db');
    }

    private function getDatabaseConnection(): SQLite3
    {
        $dbPath = wp_get_upload_dir()['basedir'] . '/' . $this->databasePath;

        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        if (!$db) {
            throw new \Exception(__('Failed to open database at', 'wp-whobird') . ' ' . $dbPath);
        }

        return $db;
    }

    private function getStartTime(): int
    {
        $isoDate = get_the_date('Y-m-d');
        $date = new DateTime($isoDate);
        return $date->getTimestamp() * 1000; // Convert to milliseconds
    }

    private function getEndTime(): int
    {
        return $this->getStartTime() + 24 * 60 * 60 * 1000; // Add 24 hours in milliseconds
    }

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

    private function playerDisplay(): string
    {
        return '<div id="audio-player-container">
            <div id="audio-player">
            <div class="player">
            <button id="prev" class="control-button">⏮</button>
            <button id="play-pause" class="control-button">▶️</button>
            <button id="next" class="control-button">⏭</button>
            </div>
            <div class="track-info">
            <span id="current-track">Track 1</span>
            </div>
            </div>
            </div>';
    }

    private function getObservationsList(): string
    {
        $db = $this->getDatabaseConnection();
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();

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
                $row['SpeciesName'],
                $this->getRecordingsUrls($row['timestamps'])
            );
            $list .= $listItemRenderer->render();
        }

        return $list;
    }

    public function displayObservations(): string
    {
        $list = $this->getObservationsList();

        if (empty($list)) {
            return '<div class="wpwbd_observations wpwbd_empty_observations">' .
                   '<p>' . __('We did not identify any birds with the WhoBIRD application today.', 'wp-whobird') . '</p>' .
                   '</div>';
        } else {
            return '<div class="wpwbd_observations">' .
                   '<p>' . __('Today we heard and identified the following birds with the WhoBIRD application:', 'wp-whobird') . '</p>' .
                   '<ul class="wpwbd_list">' .
                   $list .
                   '</ul>' .
                   $this->playerDisplay() .
                   '</div>';
        }
    }
}
