<?php

$mission = array();
$mission['name'] = 'UND LMAH 7';

// Name for mission control and the habitat. 
$mission['mcc_name'] = 'Mission Control';
$mission['hab_name'] = 'Mars Habitat';

// Name of home and away planet. 
$mission['home_planet'] = 'Earth';
$mission['away_planet'] = 'Mars';

// Name for user roles. 
$mission['role_hab'] = 'Astronaut';
$mission['role_mcc'] = 'Mission Control';


$mission['time_day'] = 'Mission Sol';
$mission['time_epoch_hab'] = '2021-08-10 00:00:00'; // ISO
$mission['time_hab_format'] = true;
$mission['timezone_mcc'] = 'America/New_York';
$mission['timezone_hab'] = 'America/Chicago';
$mission['time_sec_per_day'] = 24 * 60 * 60;

?>