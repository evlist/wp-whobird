<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<p <?php echo get_block_wrapper_attributes(); ?>>
<?php 

if (! class_exists('wpwbdDB')) {
    class wpwbdDB extends SQLite3
    {
        function __construct()
        {
            $dbPath = wp_get_upload_dir()['basedir'].'/WhoBird/databases/BirdDatabase.db';
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

if (! function_exists('wpwbdGetObservationsList')) {
    function wpwbdGetObservationsList() 
    {
        $db = new wpwbdDB();
        $startTime = wpwbdGetStartTime();
        $endTime = wpwbdGetEndTime();
        $results = $db->query("SELECT BirdNET_ID, SpeciesName from BirdObservations where TimeInMillis >= $startTime and TimeInMillis < $endTime and Probability > .5 group by BirdNET_ID order by min(TimeInMillis)");
        $list='';
        while ($row = $results->fetchArray()) {
            $list .= '<li>';
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
