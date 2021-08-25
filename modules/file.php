<?php

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
        $file = false;

        if($this->user != null)
        {
            $messageFileDao = MessageFileDao::getInstance();
            $file = $messageFileDao->getFile($fileId, $this->user->user_id);
        }

        // Also catches the case where the user does not have 
        // access to the image (because they are guessing files 
        // or trying to access a file without being logged in)
        if($file == null || !$file->exists()) 
        {
            header("HTTP/1.1 404 Not Found");
            return;
        }

        $filepath = $file->getServerPath();
        $mimeType = $file->mime_type;
        $origName = $file->original_name;
        $filesize = $file->size;
        $templateType = $file->getTemplateType();

        if(!isset($_GET['download']) && in_array($templateType, array('image', 'video', 'audio')))
        {
            // Display inline. 
            header('Content-Disposition: filename='.basename($origName));
        }
        else
        {
            // Force file download
            header('Content-Disposition: attachment; filename='.basename($origName));
        }
        header('Content-Length: ' . $filesize);
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

            echo Main::loadTemplate($filepath, array(), '');
    }
}

?>