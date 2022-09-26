<?php

/**
 * FileModule allows users to access:
 * - Attachments - Protected content
 * - Archives - Protected content
 * - CSS/JS - Customized for site
 * 
 * @link https://github.com/dschor5/ECHO
 */
class FileModule implements Module
{
    private $user;

    /**
     * Constructor. Tracks user just to know whether they 
     * are allowed to see a file or not.
     *
     * @param User $user Current logged in user. 
     */
    public function __construct(&$user)
    {
        $this->user = &$user;
    }

    /**
     * Overrides compile module to send proper HTTP headers 
     * and file contents depending on whether this is downloading
     * an attachment, css/js file, or an archive.
     *
     * @return string
     */
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
            $this->getFileUpload($id);
        }
        
        // Unlike other pages, the compile ends once the 
        // file is donwloaded.
        exit();
    }

    /**
     * Gets a file attachment (audio, video, or other file). 
     * 
     * If the file is not found or the user does not have access, then
     * the function will issue an HTTP 404 not found error. 
     * 
     * If the file is found and the user has access, then the HTTP headers
     * will vary depending on whether it is a file to display inline 
     * (e.g., image, video, or audio) or whether it is an attachment to download.
     *
     * @param integer $fileId Note that the file id is the same as the message id.
     * @return void
     */
    private function getFileUpload(int $fileId)
    {
        $file = false;

        // Only search the database if the user is valid. No point 
        if($this->user != null)
        {
            $messageFileDao = MessageFileDao::getInstance();
            $file = $messageFileDao->getFile($fileId, $this->user->user_id);
        }

        // Also catches the case where the user does not have 
        // access to the image (because they are guessing files 
        // or trying to access a file without being logged in)
        if($file === false || $file == null || !$file->exists()) 
        {
            header("HTTP/1.1 404 Not Found");
            return;
        }

        // Get file information to start the download process. 
        $filepath = $file->getServerPath();
        $mimeType = $file->mime_type;
        $origName = $file->original_name;
        $filesize = $file->size;
        $templateType = $file->getTemplateType();

        // The download will use the original file name, however, the data is retrieved 
        // from the server location that uses a different name altogether.
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

    /**
     * Parse a JS or CSS file to incorporate site specific information 
     * that we don't want to have to reconfigure manually if installing 
     * on another platform. 
     *
     * @param string $filename
     * @return void
     */
    private function parseFile(string $filename)
    {
        global $config;

        // Extract the extension and build a string to the proper file path.
        $extension = substr($filename, strrpos($filename, '.') + 1);
        $filepath = '/'.$extension.'/'.$filename;

        // If not found (e.g., invalid name) then return not found.
        if(!file_exists($config['templates_dir'].$filepath))
        {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        // Else send the proper HTTP headers.
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

    /**
     * Download an archive. 
     *
     * @param integer $archiveId
     * @return void
     */
    private function downloadArchive(int $archiveId)
    {
        $archive = false;

        // Only admins can get an archive. That is checked by the query. 
        if($this->user != null)
        {
            $archiveDao = ArchiveDao::getInstance();
            $archive = $archiveDao->getArchive($archiveId, $this->user->user_id);
        }

        // Also catches the case where the user does not have 
        // access to the image (because they are guessing files 
        // or trying to access a file without being logged in)
        if($archive === false || $archive == null || !$archive->exists()) 
        {
            Logger::warning('file::downloadArchive failed to download '.$archiveId.'.');
            header("HTTP/1.1 404 Not Found");
            return;
        }

        $filepath = $archive->getServerPath();
        $mimeType = $archive->mime_type;
        $origName = 'archive-'.$archive->archive_id.'-'.
            $archive->getTimestamp(DelayTime::DATE_FORMAT_FILE).'.'.$archive->getExtension();
        $filesize = $archive->size;

        header('Content-Disposition: attachment; filename='.basename($origName));
        header('Content-Length: ' . $filesize);
        header("Content-Type: ".$mimeType);
        readfile($filepath);
    }
}

?>