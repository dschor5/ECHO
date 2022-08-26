<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('server.inc.php');

$config = array();
$config['echo_version'] = '3.2';
$config['templates_dir'] = 'templates';
$config['modules_dir'] = 'modules';
$config['uploads_dir'] = 'uploads';
$config['logs_dir'] = 'logs';
$config['log_file'] = 'analog.log';

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
    'file'
);

$config['modules_user'] = array_merge($config['modules_public'], array(
    'chat',
    'help'
));

$config['modules_admin'] = array_merge($config['modules_user'], array( 
    'admin',
    'debug'
));

$config['cookie_name'] = 'website';

// include some files that we need to run.
require_once('database/database.php');
require_once('database/dao.php');
require_once('modules/interface.php');
require_once('modules/default.php');
require_once('modules/error.php');
require_once('modules/debug.php');

// Include classes
require_once('class/log.php');
require_once('class/delay.php');
require_once('class/time.php');
require_once('class/serverFile.php');
require_once('class/message.php');
require_once('class/user.php');
require_once('class/list.php');
require_once('class/mission.php');
require_once('class/conversation.php');
require_once('class/attachment.php');
require_once('class/archive.php');
require_once('class/parsedown.php');

// Include database objects
require_once('database/missionDao.php');
require_once('database/usersDao.php');
require_once('database/conversationsDao.php');
require_once('database/participantsDao.php');
require_once('database/messagesDao.php');
require_once('database/messageStatusDao.php');
require_once('database/messageFileDao.php');
require_once('database/archiveDao.php');



?>
