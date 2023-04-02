<?php

/**
 * Data Abstraction Object for the files table. Implements custom 
 * queries to search and update conversations as needed. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class FilesDao extends Dao
{
    /**
     * Singleton instance for FilesDao object.
     * @access private
     * @var FilesDao
     **/
    private static $instance = null;

    /**
     * Returns singleton instance of this object. 
     * 
     * @return FilesDao
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new FilesDao();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instances of this object.
     **/
    protected function __construct()
    {
        parent::__construct('files', 'file_id');
    }

    /**
     * Get files associated with a particular mission archive.
     *
     * @param int $archiveId Archive id containing the mission archive.
     * @param int $userId Checks that the given user can download the archive.
     * @return MissionArchive|null 
     **/
    public function getArchive(int $archiveId, int $userId) 
    {
        $qArchiveId = '\''.$this->database->prepareStatement($archiveId).'\'';
        $qUserId = '\''.$this->database->prepareStatement($userId).'\'';
        
        // Get files with the matching file_id and ensure the user is
        // an admin that can read the archive.
        $queryStr = 'SELECT files.* '. 
                    'FROM `files`, `users` '. 
                    'WHERE files.file_id='.$qArchiveId.' AND '.
                       'files.association="archive" AND '. 
                    'users.user_id='.$qUserId.' AND users.is_admin=1';

        $file = null;

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                $file = new MissionArchive($result->fetch_assoc());
            }
        }

        return $file;
    }

    /**
     * Get list of all archives. 
     *
     * @return array 
     */
    public function getArchives(): array
    {
        $archives = array();
        if(($result = $this->select('*','association="archive"','timestamp','ASC')) !== false)
        {   
            if($result->num_rows > 0)
            {
                while(($data = $result->fetch_assoc()) != null)
                {
                    $archives[$data['file_id']] = new MissionArchive($data);
                }
            }
        }

        return $archives;
    }

    /**
     * Get files associated with a particular message id and visible by the given user id. 
     *
     * @param int $messageId Message id containing the file attachment. 
     * @param int $userId Checks that the given user can read the file in this conversation. 
     * @return FileUpload|null 
     **/
    public function getFile(int $fileId, int $userId) 
    {
        $qFileId = '\''.$this->database->prepareStatement($fileId).'\'';
        $qUserId = '\''.$this->database->prepareStatement($userId).'\'';
        
        // Get files with the matching message_id and ensure the user is
        // part of the conversation (thus having read access).
        $queryStr = 'SELECT files.* FROM files '.
                    'JOIN messages ON messages.file_id=files.file_id '.
                    'JOIN participants ON participants.conversation_id=messages.conversation_id '.
                    'WHERE files.file_id='.$qFileId.' AND participants.user_id='.$qUserId;

        $file = null;

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                $file = new FileUpload($result->fetch_assoc());
            }
        }

        return $file;
    }

    public function insert(array $fields, array $variables=array())
    {
        Logger::info('MADE IT!');

        if(isset($fields['uuid']))
        {
            unset($fields['uuid']);
        }

        $variables['uuid'] = 'UUID_TO_BIN(UUID())';

        return Dao::insert($fields, $variables);
    }
}

?>
