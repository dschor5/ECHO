<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
putenv("TZ=US/Central");

$config = array();

$config['templates_dir'] = 'templates';
$config['modules_dir'] = 'modules';

// this is the url of the site (WITHOUT http:// OR trailing slash)
$config['host_address'] = './';
$config['http'] = 'http://';
$config['site_url'] = '127.0.0.1';

$config['date_format'] = 'M j, Y, g:i a';
$config['date_format_short'] = 'm/d/Y';
$config['date_format_notime'] = 'M j, Y';

// this is the array of modules that are allowed to run
$config['modules_public'] = array(
    'home',
    'error',
    'login'
);

$config['modules_user'] = array(
    'chat',
    'ajax'
);

$config['modules_admin'] = array(
    'users', 
    'delay', 
    'mission'
);

$config['cookie_name'] = 'website';
$config['cookie_expire'] = 3600;

// include some files that we need to run.
require_once('database/database.php');
require_once('database/dao.php');
require_once('database/result.php');
require_once('modules/default.php');

// Include classes
require_once('class/message.php');
require_once('class/user.php');
require_once('class/delay.php');

// mySQL database login info
$config['db_host'] = '127.0.0.1';
$config['db_user'] = 'user';
$config['db_pass'] = 'password';
$config['db_name'] = 'database';

//these are the dao's that are allowed to load.  Add new tables to this list if you need.
$config['db_tables'] = array(
    'users',
    'messages',
    'threads'
);

foreach ($config['db_tables'] as $table)
{
    require_once($config['host_address']."database/".strtolower($table). "Dao.php");
}

?>
