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

    public function __get(string $name)
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

    public static function getMaxUploadSize() : int
    {
        $maxSize = -1;

        // Get POST_MAX_SIZE from php.ini settings. 
        $maxSize = max($maxSize, self::parseSize(ini_get('post_max_size')));

        // Get UPLOAD_MAX_SIZE from php.ini settings. 
        // - If $uploadMax == 0 --> no limit. 
        // - Elseif $uploadMax < $maxSize --> reduce the size. 
        $uploadMax = self::parseSize(ini_get('upload_max_filesize'));
        if($uploadMax > 0 && $uploadMax < $maxSize)
        {
            $maxSize = $uploadMax;
        }
        return $maxSize;
    }

    private static function parseSize($size) : int
    {
        // Remove non-unit characters from the size.
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); 
        // Remove non-numeric characters from the size. 
        $size = preg_replace('/[^0-9\.]/', '', $size); 

        if($unit)
        {
            // Use position of unit in ordered string as the power/magnitude to multiply the bytes. 
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        // Else, it is a byte limit, so return as is. 
        return round($size);
    }    

    public function getServerPath()
    {
        global $server;
        global $config;
        return $server['host_address'].$config['uploads_dir'].'/'.$this->data['server_name']; 
    }

    public static function getHumanReadableSize(int $size) : string 
    {
        $bytes = $size;
        $human = '0 B';
        if($bytes > 0)
        {
            $sz = 'BKMGTP';
            $factor = floor((strlen($bytes) - 1) / 3);
            $human = sprintf("%.2f %s", $bytes / pow(1024, $factor),  substr($sz, $factor, 1));
        }
        return $human;
    }

    public function getSize() : string 
    {
        return self::getHumanReadableSize($this->size);
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