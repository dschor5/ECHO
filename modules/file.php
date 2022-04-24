<?php

class FileModule implements Module
{
    private $user;

    public function __construct(&$user)
    {
        $this->user = &$user;
    }

    public function compile() : string
    {
        $subaction = $_GET['subaction'] ?? '';
        $id = $_GET['id'] ?? '';
        
        if($subaction == 'archive' && intval($id) > 0)
        {
            // Download archive file
            $this->downloadArchive($id);
        }
        else if($subaction == 'css' || $subaction == 'js')
        {
            // Parse JS or CSS file
            $this->parseFile($id);
        }
        else if($subaction == 'file' && intval($id) > 0)
        {
            // Get file attachment
            Logger::warning('compile: '.$id.'.');
            $this->getFileUpload($id);
        }
        else
        {
            Logger::warning('FileModule::compile - Invalid subaction='.strval($subaction).', id='.strval($id));
        }

        exit();
    }

    private function getFileUpload(int $fileId)
    {
        $file = false;

        if($this->user != null)
        {
            $messageFileDao = MessageFileDao::getInstance();
            $file = $messageFileDao->getFile($fileId, $this->user->user_id);
            Logger::warning('getFileUpload: '.$fileId. ' - '.($file->getServerPath()).'.');
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

    private function parseFile(string $filename)
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

    private function downloadArchive(int $archiveId)
    {
        $archive = false;

        if($this->user != null)
        {
            $archiveDao = ArchiveDao::getInstance();
            $archive = $archiveDao->getArchive($archiveId, $this->user->user_id);
        }

        // Also catches the case where the user does not have 
        // access to the image (because they are guessing files 
        // or trying to access a file without being logged in)
        if($archive == null || !$archive->exists()) 
        {
            Logger::warning('file::downloadArchive failed to download '.$archiveId.'.');
            header("HTTP/1.1 404 Not Found");
            return;
        }

        $filepath = $archive->getServerPath();
        $mimeType = $archive->mime_type;
        $origName = 'archive-'.$archive->archive_id.'-'.$archive->getFilenameTimestamp().'.'.$archive->getExtension();
        $filesize = $archive->size;

        header('Content-Disposition: attachment; filename='.basename($origName));
        header('Content-Length: ' . $filesize);
        header("Content-Type: ".$mimeType);
        readfile($filepath);
    }
}

?>