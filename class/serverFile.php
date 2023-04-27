<?php

/**
 * ServerFile is an abstract class from which MissionArchive and FileUpload
 * are derived. 
 * 
 * Implementation Notes:
 * - Archives are renamed with a unique id of numeric characters
 *   with extension TMP before being saved to the logs directory. 
 * - The 'mission_archives' database table is used to map the names:
 *      - original_name --> Original filename as uploaded by the user.
 *      - server_name   --> New name assiged by the application.
 * - Renaming helps to:
 *      1. Prevents accidentally overriding files if a user uploads 
 *         multiple version of the same file. 
 *      2. Hides upload file names to reduce the likelihood of someone 
 *         being able to read information they are not suppowed to see. 
 * 
 * 
 * @link https://github.com/dschor5/ECHO
 */
 class ServerFile
{
    /**
     * Data from database table. 
     * @access protected
     * @var array
     */
    protected $data;

    /**
     * Folder used in path
     * @access private
     * @var string
     */
    private $folder;

    /**
     * Constant extension used to rename all attachments saved to the server.
     * @access protected
     * @var string
     */
    const TEMP_EXT = 'tmp';

    /**
     * ServerFile constructor. Appends object with field 'size' that
     * contains the size of the file in bytes.
     * 
     * @param array $data Row from database table. 
     */
    public function __construct(array $data, string $folder)
    {
        $this->data = $data; 
        $this->folder = $folder;
        $this->data['size'] = $this->exists() ? filesize($this->getServerPath()) : 0;
    }

    /**
     * Accessor for database fields. Returns value stored in the field $name 
     * or null if the field does not exist. 
     * 
     * @param string $name Name of field being requested. 
     * @return mixed Value contained by the field requested. 
     */
    public function __get(string $name) : mixed
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $result = $this->data[$name];
        }
        else
        {
            Logger::warning(get_class($this).' __get("'.$name.'")', $this->data);
        }

        return $result;
    }

    /**
     * Returns true if the file referenced by the object exists on the server.
     * @return bool True if file exists.
     */
    public function exists() : bool
    {
        return file_exists($this->getServerPath());
    }

    /**
     * Generates a random and unique filename to used when saving an attachment
     * to the server. The function checks that the filename is not already in use. 
     * 
     * NOTE: The function does nto reserve the name. Therefore, there is a finite 
     *       chance that two users are uploading files at the same time and and 
     *       they overwrite each other because the server generates the same name. 
     *       Because of the small number of users in an analog mission, this is 
     *       considered a low risk and not worth the effort to use a mutex. 
     * 
     * @return string Random generated filename. 
     */
    public static function generateFilename(string $folder) : string
    {
        global $config;

        do 
        {
            $filename = mt_rand().'.'.self::TEMP_EXT;
            $fullpath = $folder.'/'.$filename;
        } while(file_exists($fullpath));

        return $filename;
    }

    /**
     * Returns the maximum upload size in bytes allowed by PHP.ini. 
     * 
     * @return int Max upload size in bytes.
     */
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

    /**
     * Parse PHP.ini filesize parameter into bytes. 
     * 
     * @param string $size Representation of the size. 
     * @return int Number of bytes.
     */
    private static function parseSize(string $size) : int
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

    /**
     * Return the server path to the file. 
     *
     * @return string Full path to the file on the server. 
     */
    public function getServerPath() : string
    {
        global $server;
        return $server['host_address'].$this->folder.'/'.$this->data['server_name']; 
    }

    /**
     * Return a human-readable filesize. 
     * 
     * @param int $size File size in bytes.
     * @return string Human readable file size.
     */
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

    /**
     * Get the human readable file size for the file tracked by this object.
     * 
     * @return string Human-readable file size.
     */
    public function getSize() : string 
    {
        return self::getHumanReadableSize($this->size);
    }
}

?>