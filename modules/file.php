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

    public function compile() : string
    {
        global $config;
        global $mission;

        $id = $_GET['id'] ?? '';
        
        if(intval($id) > 0)
        {
            $this->getFileUpload($id);
        }
        else 
        {
            $this->parseFile($id);
        }

        exit();
    }

    private function getFileUpload($fileId)
    {
        $messageFileDao = MessageFileDao::getInstance();
        $file = $messageFileDao->getFile($fileId, $this->user->getId());

        if($file == null) 
        {
            header("HTTP/1.1 404 Not Found");
            return;
        }

        $filepath = $file->getServerPath();
        $mimeType = $file->getMimeType();
        $origName = $file->getOriginalName();
        $type = explode('/', $mimeType, 2)[0];
        
        if($type == 'image' || $type == 'video' || $type == 'audio')
        {
            // Display inline. 
            header('Content-Disposition: filename='.basename($origName));
        }
        else
        {
            // Force file download
            header('Content-Disposition: attachment; filename='.basename($file->getOriginalName()));
        }
        header('Content-Length: ' . filesize($filepath));
        header("Content-Type: ".$mimeType);
        readfile($filepath);
    }

    private function parseFile($filename)
    {
        global $config;

        $extension = substr($filename, strrpos($filename, '.') + 1);

        $filepath = '/'.$extension.'/'.$filename;

            if(!file_exists($config['templates_dir'].$filepath))
            {
                header("HTTP/1.1 404 Not Found");
                exit();
            }

            switch($extension)
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
}

?>