<?php

//don't cache this page
header('Pragma: no-cache');

//include config file
include_once('config.inc.php');

$filename = $_GET['f'] ?? '';
$filepath = $config['templates_dir'].'/'.$filename;

if(!file_exists($filepath))
{
    header("HTTP/1.1 404 Not Found");
    exit();
}

$fileinfo = pathinfo($filepath);
switch($fileinfo['extension'])
{
    case 'css':
        header('Content-Type: text/css');
        break;
    case 'js':
        header('Content-Type: text/javascript');
        break;
    default:
        header('Content-Type: text/plain');
        break;
}

echo Main::loadTemplate($filepath);

?>
