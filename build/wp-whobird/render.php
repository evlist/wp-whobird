<?php
/**
 * vim: set ai sw=4 smarttab expandtab: tabstop=8 softtabstop=0
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<p <?php echo get_block_wrapper_attributes(); ?>>
<?php 

class wpwbdDB extends SQLite3
{
    function __construct()
    {
        $dbPath = wp_get_upload_dir()['basedir'].'/WhoBird/databases/BirdDatabase.db';
        $this->open($dbPath, SQLITE3_OPEN_READONLY);
    }
}       

function wpwbdGetStartTime() {
    $iso_date=get_the_date('Y-m-d');
    $date=new DateTime($iso_date);
    return $date->getTimestamp() * 1000;
}

function wpwbdGetEndTime() {
    return wpwbdGetStartTime() + 24 * 60 * 60 * 1000;
}

function wpwbdDisplayObservations() 
{

    //phpinfo();

    $db = new wpwbdDB();
    $startTime = wpwbdGetStartTime();
    $endTime = wpwbdGetEndTime();
    $results = $db->query("SELECT BirdNET_ID, SpeciesName from BirdObservations where TimeInMillis >= $startTime and TimeInMillis < $endTime and Probability > .5 group by BirdNET_ID order by min(TimeInMillis)");
    echo '<div class="wpwbd_observations">';
    echo '<p>Aujourd\'hui nous avons entendu les oiseaux suivants : </p>';
    echo '<ul class="wpwbd_list">';
    while ($row = $results->fetchArray()) {
        echo '<li>';
        echo $row['SpeciesName'];
        echo '</li>';
    }
    echo '</ul>';
    echo '</div>';

}

wpwbdDisplayObservations();

?>
</p>
