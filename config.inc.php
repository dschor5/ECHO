<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
putenv("TZ=US/Eastern");

require_once('server.inc.php');
require_once('mission.inc.php');

$config = array();
$config['templates_dir'] = 'templates';
$config['modules_dir'] = 'modules';

// this is the array of modules that are allowed to run
$config['modules_public'] = array(
    'home',
    'error',
    'login',
    'file'
);

$config['modules_user'] = array_merge($config['modules_public'], array(
    'chat',
    'settings'
));

$config['modules_admin'] = array_merge($config['modules_user'], array(
    'users', 
    'delay', 
    'mission'
));

$config['cookie_name'] = 'website';
$config['cookie_expire'] = 3600;

//these are the dao's that are allowed to load.  Add new tables to this list if you need.
$config['db_tables'] = array(
    'users',
    'messages',
    'conversations'
);

// include some files that we need to run.
require_once('database/database.php');
require_once('database/dao.php');
require_once('database/result.php');
require_once('modules/default.php');
require_once('database/databaseException.php');

// Include classes
require_once('class/message.php');
require_once('class/user.php');
require_once('class/delay.php');
require_once('class/time.php');
require_once('class/list.php');
require_once('class/conversation.php');

// Include database objects
require_once('database/usersDao.php');
require_once('database/conversationsDao.php');
require_once('database/participantsDao.php');
require_once('database/messagesDao.php');
require_once('database/messageStatusDao.php');

?>
