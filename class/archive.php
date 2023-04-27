<?php

/**
 * MissionArchive objects represent an archive of all conversations/attachments.  
 * Encapsulates 'mission_archives' row from database.
 * 
 * Table Structure: 'archives'
 * - archive_id     (int)       Unique id for each archive.
 * - server_name    (string)    Server name where archive is stored
 * - notes          (string)    Notes saved with the archive (RFU)
 * - mime_type      (string)    Mime type for current archive
 * - timestamp      (datetime)  UTC timestamp when the archive was created
 * - content_tz     (string)    Timezone used in archive
 * 
 * Implementation Notes:
 * - Archives are renamed with a unique id of numeric characters
 *   with extension TMP before being saved to the logs directory. 
 * - The 'mission_archives' database table is used to map the names:
 *      - original_name --> Original filename as uploaded by the user.
 *      - server_name   --> New name assiged by the application.
 * - Renaming helps to:
 *      1. Prevents filename collisions.
 *      2. Hides upload file names to reduce the likelihood of someone 
 *         being able to read information they are not suppowed to see. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class MissionArchive extends ServerFile
{
    /**
     * Constant definition of valid archive types.
     * @access private
     * @var array of strings
     */
    const ARCHIVE_TYPES = array(
        'application/sql' => array('ext' => 'sql', 'desc' => 'Mysql Backup (SQL)'),
        'application/zip' => array('ext' => 'zip', 'desc' => 'Conversation Backup (ZIP)'),
        'application/txt' => array('ext' => 'txt', 'desc' => 'Log Backup (TXT)'),
    );

    /**
     * MissionArchive constructor. Appends object with field 'size' that
     * contains the size of the file in bytes.
     * 
     * @param array $data Row from 'mission_archives' database table. 
     */
    public function __construct($data)
    {
        global $config;
        parent::__construct($data, $config['logs_dir']);
    }

    /**
     * Accessor for MissionArchive fields. Returns value stored in the field $name 
     * or null if the field does not exist. 
     * 
     * @param string $name Name of field being requested. 
     * @return mixed Value contained by the field requested. 
     */
    public function __get($name) : mixed
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $result = $this->data[$name];
        }
        else
        {
            Logger::warning('MissionArchive __get("'.$name.'")', $this->data);
        }

        return $result;
    }

    /**
     * Returns the timestamp in the MCC timezone when the archive was created. 
     *
     * @param string $format Format for timestamp. Default DATE_FORMAT.
     * @return string Timestamp in MCC timezone and format selected.
     **/
    public function getTimestamp(string $format=DelayTime::DATE_FORMAT) : string
    {
        $mission = MissionConfig::getInstance();
        return DelayTime::convertTimestampTimezone($this->timestamp, 'UTC', $mission->mcc_timezone, $format);
    }

    /**
     * Gets description of archive type based on mime type in database.
     *
     * @return string 
     */
    public function getType(bool $short=true) : string
    {
        $archiveType = '';

        if(array_key_exists($this->mime_type, MissionArchive::ARCHIVE_TYPES))
        {
            if($short)
            {
                $archiveType = strtoupper(MissionArchive::ARCHIVE_TYPES[$this->mime_type]['ext']);
            }
            else
            {
                $archiveType = strtoupper(MissionArchive::ARCHIVE_TYPES[$this->mime_type]['desc']);
            }
        }
        else 
        {
            Logger::warning('MissionArchive::getType() invalid mime_type='.$this->mime_type);
	    }

        return $archiveType;
    }

    /**
     * Gets extension of archive type based on mime type in database.
     *
     * @return string 
     */
    public function getExtension() : string
    {
        $type = '';

        if(array_key_exists($this->mime_type, MissionArchive::ARCHIVE_TYPES))
        {
            $type = MissionArchive::ARCHIVE_TYPES[$this->mime_type]['ext'];
        }
        else 
        {
            Logger::warning('MissionArchive::getExtension() invalid mime_type='.$this->mime_type);
	    }

        return $type;
    }
}

?>