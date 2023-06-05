<?php

error_reporting(E_ALL);
header('Pragma: no-cache');
date_default_timezone_set("UTC");
require_once('config.inc.php');

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
require_once('class/archiveMaker.php');
require_once('class/schedMessage.php');

// Include database objects
require_once('database/missionDao.php');
require_once('database/usersDao.php');
require_once('database/conversationsDao.php');
require_once('database/participantsDao.php');
require_once('database/messagesDao.php');
require_once('database/messageStatusDao.php');
require_once('database/messageFileDao.php');
require_once('database/archiveDao.php');
require_once('database/schedMessagesDao.php');

$schedMessagesDao = SchedMessagesDao::getInstance();
$schedMessagesDao->sendScheduledMessages();

?>