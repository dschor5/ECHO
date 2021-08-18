<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('server.inc.php');
require_once('mission.inc.php');

$config = array();
$config['debug'] = true;
$config['templates_dir'] = 'templates';
$config['modules_dir'] = 'modules';
$config['uploads_dir'] = 'uploads';

// Extension => Mime Type
$config['uploads_allowed'] = array(
    'application/msword' => 'doc' ,
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/pdf' => 'pdf' ,
    'text/plain' => 'txt' ,
    'application/vnd.ms-powerpoint' => 'ppt' ,
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-excel' => 'xls' ,
    'text/csv' => 'csv' ,
    'image/bmp' => 'bmp' ,
    'image/gif' => 'gif' ,
    'image/jpg' => 'jpg' ,
    'image/jpeg'  => 'jpeg',
    'image/png' => 'png' ,
    'image/tiff' => 'tif' ,
    'image/tiff' => 'tiff',
    'application/vnd.rar' => 'rar' ,
    'application/x-tar' => 'tar' ,
    'application/vnd.visio' => 'vsd' ,
    'audio/webm' => 'mka' ,
    'video/webm' => 'mkv' ,
    'video/x-matroska' => 'mkv' ,
    'application/zip' => 'zip' ,
    'application/x-7z-compressed' => '7z',
);

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
$config['cookie_expire'] = 1800;

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
require_once('class/delay.php');
require_once('class/time.php');
require_once('class/message.php');
require_once('class/user.php');
require_once('class/list.php');
require_once('class/conversation.php');
require_once('class/file.php');

// Include database objects
require_once('database/usersDao.php');
require_once('database/conversationsDao.php');
require_once('database/participantsDao.php');
require_once('database/messagesDao.php');
require_once('database/messageStatusDao.php');
require_once('database/messageFileDao.php');



?>
