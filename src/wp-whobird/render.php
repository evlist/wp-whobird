<?php
namespace WPWhoBird;

use DateTime;
use SQLite3;

class WhoBirdRenderer
{
    private string $recordingsPath;
    private string $databasePath;

    public function __construct()
    {
        $this->recordingsPath = get_option('wpwhobird_recordings_path', 'WhoBird/recordings');
        $this->databasePath = get_option('wpwhobird_database_path', 'WhoBird/databases/BirdDatabase.db');
    }

    private function getDatabaseConnection(): SQLite3
    {
        $dbPath = wp_get_upload_dir()['basedir'] . '/' . $this->databasePath;

        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        if (!$db) {
            throw new \Exception(__('Failed to open database at', 'wpwhobird') . ' ' . $dbPath);
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

        foreach ($recordingIds as $recordingId) {
            $recordingPath = $uploadDir['basedir'] . '/' . $this->recordingsPath . '/' . $recordingId . '.wav';
            if (is_file($recordingPath) && is_readable($recordingPath)) {
                $recordingUrl = $uploadDir['baseurl'] . '/' . $this->recordingsPath . '/' . $recordingId . '.wav';
                $recordingUrls[] = $recordingUrl;
            }
        }

        return implode(',', $recordingUrls);
    }

    private function getObservationsList(): string
    {
        $db = $this->getDatabaseConnection();
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();

        $query = $db->prepare(
            "SELECT BirdNET_ID, SpeciesName, group_concat(TimeInMillis) as timestamps 
             FROM BirdObservations 
             WHERE TimeInMillis >= :startTime AND TimeInMillis < :endTime AND Probability >= :threshold 
             GROUP BY BirdNET_ID, SpeciesName"
        );

        $query->bindValue(':startTime', $startTime, SQLITE3_INTEGER);
        $query->bindValue(':endTime', $endTime, SQLITE3_INTEGER);
        $query->bindValue(':threshold', 0.7, SQLITE3_FLOAT);

        $results = $query->execute();

        $list = '';
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $list .= '<li data-recordings="' . esc_attr($this->getRecordingsUrls($row['timestamps'])) . '">';
            $list .= esc_html($row['SpeciesName']);
            $list .= '</li>';
        }

        return $list;
    }

    public function displayObservations(): void
    {
        $list = $this->getObservationsList();

        if (empty($list)) {
            echo '<div class="wpwbd_observations wpwbd_empty_observations">';
            echo '<p>' . __('We did not identify any birds with the WhoBIRD application today.', 'wpwhobird') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="wpwbd_observations">';
            echo '<p>' . __('Today we heard and identified the following birds with the WhoBIRD application:', 'wpwhobird') . '</p>';
            echo '<ul class="wpwbd_list">';
            echo $list;
            echo '</ul>';
            echo '</div>';
        }
    }
}

// Initialize and render the observations
$renderer = new WhoBirdRenderer();
$renderer->displayObservations();
?>
