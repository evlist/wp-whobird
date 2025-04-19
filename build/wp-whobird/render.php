<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<p <?php echo get_block_wrapper_attributes(); ?>>
<?php 

global $wpwbd_recordings_path; 
global $wpwbd_database_path;
$wpwbd_recordings_path = 'WhoBird/recordings';
$wpwbd_database_path = 'WhoBird/databases/BirdDatabase.db';

if (! class_exists('wpwbdDB')) {
    class wpwbdDB extends SQLite3
    {
        function __construct()
        {
            global $wpwbd_database_path;
            $dbPath = wp_get_upload_dir()['basedir'].'/'. $wpwbd_database_path;
            $this->open($dbPath, SQLITE3_OPEN_READONLY);
        }
    }       
}

if (! function_exists('wpwbdGetStartTime')) {
    function wpwbdGetStartTime() {
        $iso_date=get_the_date('Y-m-d');
        $date=new DateTime($iso_date);
        return $date->getTimestamp() * 1000;
    }
}

if (! function_exists('wpwbdGetEndTime')) {
    function wpwbdGetEndTime() {
        return wpwbdGetStartTime() + 24 * 60 * 60 * 1000;
    }
}

if (! function_exists('wpwdbGetRecoringsUrls')) {
    function wpwbdGetRecordingsUrls($recordingIdsString) {
        global $wpwbd_recordings_path;
        $recordingIds = explode(',', $recordingIdsString);
        $recordingUrls = array();
        $uploadDir = wp_get_upload_dir();
        foreach ($recordingIds as $recordingId) {
            $recordingPath = $uploadDir['basedir'].'/'. $wpwbd_recordings_path. '/' . $recordingId . '.wav';
            if (is_readable($recordingPath) && is_file($recordingPath)) {
                $recordingUrl = $uploadDir['baseurl'].'/'.$wpwbd_recordings_path.'/'.$recordingId.'.wav';
                $recordingUrls[] = $recordingUrl;
            }
        }
        return join(',', $recordingUrls);
    }
}

if (! function_exists('wpwbdGetObservationsList')) {
    function wpwbdGetObservationsList() 
    {
        $db = new wpwbdDB();
        $startTime = wpwbdGetStartTime();
        $endTime = wpwbdGetEndTime();
        $results = $db->query("SELECT BirdNET_ID, SpeciesName, group_concat(TimeInMillis) as timestamps from BirdObservations where TimeInMillis >= $startTime and TimeInMillis < $endTime and Probability > .4 group by BirdNET_ID order by min(TimeInMillis)");
        $list='';
        while ($row = $results->fetchArray()) {
            $list .= '<li data-recordings="'.wpwbdGetRecordingsUrls($row['timestamps']).'">';
            $list .= $row['SpeciesName'];
            $list .= '</li>';
        }
        return $list;
    }
}

if (! function_exists('wpwbdDisplayObservations')) {
    function wpwbdDisplayObservations() 
    {

        $list = wpwbdGetObservationsList();
        if ($list == '') {
            echo '<div class="wpwbd_observations wpwbd_empty_observations">';
            echo '<p>Nous n\'avons identifié aucun oiseau avec l\'application WhoBIRD aujourd\'hui.</p>';
            echo '</div>';
        } else {
            echo '<div class="wpwbd_observations">';
            echo '<p>Aujourd\'hui nous avons entendu et identifié les oiseaux suivants avec l\'application WhoBIRD : </p>';
            echo '<ul class="wpwbd_list">';
            echo $list;
            echo '</ul>';
            echo '</div>';
        }


    }

}
wpwbdDisplayObservations();

?>
</p>
