<?php
    require_once("captiveportal.inc");
    init_config_arr(array('captiveportal'));

    $cpzone = "crew";
    $cpdb = captiveportal_read_db();

    foreach ($cpdb as $eachuser) {
        if(get_suspend_timeschedule($eachuser[4])){
            captiveportal_disconnect_client($eachuser[5]);
        }
    }
?>