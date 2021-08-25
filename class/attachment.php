<?php

class FileUpload
{
    private $data;
    const TEMP_EXT = 'tmp';

    public function __construct($data)
    {
        $this->data = $data; // requires union with corresponding msg_status
        $this->data['size'] = $this->exists() ? filesize($this->getServerPath()) : 0;
    }

    public function __get(string $name) : mixed
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $result = $this->data[$name];
        }

        return $result;
    }
    
    public function exists()
    {
        return file_exists($this->getServerPath());
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

    public function getServerPath()
    {
        global $server;
        global $config;
        return $server['host_address'].$config['uploads_dir'].'/'.$this->data['server_name']; 
    }

    public function getHumanReadableSize() : string 
    {
        $bytes = $this->size;
        $human = '0 B';
        if($bytes > 0)
        {
            $sz = 'BKMGTP';
            $factor = floor((strlen($bytes) - 1) / 3);
            $human = sprintf("%.2f %s", $bytes / pow(1024, $factor),  substr($sz, $factor, 1));
        }
        return $human;
    }

    // Extracts first part of mimetype
    public function getTemplateType()
    {
        $fileType = explode('/', $this->mime_type, 2)[0];
        if(!in_array($fileType, array('image', 'audio', 'video')))
        {
            $fileType = 'file';
        }
        return $fileType;
    }

}

?>