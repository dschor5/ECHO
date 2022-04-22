<?php

/**
 * MissionArchive objects represent an archive of all conversations/attachments.  
 * Encapsulates 'mission_archives' row from database.
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
 * @link https://github.com/dschor5/AnalogDelaySite
 */
 class MissionArchive extends ServerFile
{
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


}

?>