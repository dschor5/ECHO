<?php

require_once('index.php');
require_once('modules/default.php');

class FileModule extends DefaultModule
{
    public function __construct(&$main, &$user)
    {
        parent::__construct($main, $user);
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