<?php

class FileUpload
{
    private $data;
    const TEMP_EXT = 'tmp';

    public function __construct($data)
    {
        var_dump($data);
        $this->data = $data; // requires union with corresponding msg_status
    }

    public static function generateFilename()
    {
        global $config;

        do 
        {
            $filename = mt_rand().'.'.self::TEMP_EXT;
            $fullpath = $config['uploads_dir'].'/'.$filename;
        } while(file_exists($fullpath));

        return $filename;
    }

    public function getOriginalName()
    {
        return $this->data['original_name'];
    }

    public function getServerPath()
    {
        global $server;
        global $config;
        return $server['host_address'].$config['uploads_dir'].'/'.$this->data['server_name']; 
    }

    public function exists() : bool
    {
        return file_exists($this->getServerPath());
    }

    public function getSize() : int
    {
        $filesize = 0;
        if($this->exists())
        {
            $filesize = filesize($this->getServerPath());
        }
        return $filesize;
    }

    public function getMimeType()
    {
        return $this->data['mime_type'];
    }

    // Extracts first part of mimetype
    public function getTemplateType()
    {
        return explode('/', $this->data['mime_type'], 2)[0];
    }
}

?>