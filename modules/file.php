<?php

require_once('index.php');
require_once('modules/default.php');

class FileModule implements Module
{
    private $user;
    private $db;

    public function __construct(&$user)
    {
        $this->user = &$user;
        $this->db = Database::getInstance();
    }

    public function compile(string $subaction) : string
    {
        global $config;
        global $mission;

        $subaction = $_GET['subaction'] ?? '';

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

            echo Main::loadTemplate($filepath);
        }

        exit();
    }
}

?>