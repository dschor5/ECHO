<?php

require_once('index.php');
require_once('modules/default.php');

class FileModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array('logout');
    }

    public function compileJson(string $subaction): array
    {
        return array();
    }

    public function compileHtml(string $subaction) : string
    {
        global $config;
        global $mission;

        $filename = $_GET['f'] ?? '';
        
        if(strlen($filename) > 0 && ($subaction == 'css' || $subaction == 'js'))
        {
            $filepath = '/'.$subaction.'/'.$filename;

            if(!file_exists($config['templates_dir'].$filepath))
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
            
            $replace = array(
                '/%epoch%/'            => DelayTime::getEpoch(),
                '/%time_sec_per_day%/' => $mission['time_sec_per_day'],
                '/%time_day%/'         => $mission['time_day'],
                '/%timezone_offset%/'  => DelayTime::getTimezoneOffset(),
            );

            echo Main::loadTemplate($filepath, $replace);
        }

        exit();
    }
}

?>