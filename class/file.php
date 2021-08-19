<?php

class FileUpload
{
    private $data;
    const TEMP_EXT = 'tmp';

    public function __construct($data)
    {
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

    public function getMimeType()
    {
        return $this->data['mime_type'];
    }
}

?>