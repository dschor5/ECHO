<?php

class ParticipantsDao extends Dao
{
    /**
     * Singleton instance for ParticipantsDao object.
     * @access private
     * @var ConversationsDao
     **/
    private static $instance = null;

    /**
     * Cache participants queries into an associative array arranged by conversation id. 
     * @access private
     * @var array 
     **/
    private static $cache = array();

    /**
     * Returns singleton instance of this object. 
     * 
     * @return Delay object
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new ParticipantsDao();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instances of this object.
     **/
    protected function __construct()
    {
        parent::__construct('participants');
    }

    public function updateLastRead($convoId, int $userId, string $lastRead)
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';

        return $this->update(array('last_read' => $lastRead),
            'conversation_id='.$qConvoId.' AND user_id='.$qUserId);
    }

    public function getLastRead(int $convoId, int $userId) : string
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';

        if(($result = $this->select('last_read', 'conversation_id='.$qConvoId.' AND user_id='.$qUserId)))
        {
            if($result->num_rows > 0)
            {
               $rowData = $result->fetch_assoc();
               return $rowData['last_read'];
            }
        }

        return '0000-00-00 00:00:00';
    }

    public function getParticipantIds(int $convoId) : array
    {
        if(!isset(self::$cache[$convoId]))
        {
            $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';

            $queryStr = 'SELECT participants.user_id, users.is_crew '.
                        'FROM participants '.
                        'JOIN users ON users.user_id=participants.user_id '. 
                        'WHERE participants.conversation_id='.$qConvoId;
            
            self::$cache[$convoId] = array();

            if(($result = $this->database->query($queryStr)) !== false)
            {
                if($result->num_rows > 0)
                {
                    while(($rowData=$result->fetch_assoc()) != null)
                    {
                        self::$cache[$convoId][$rowData['user_id']] = $rowData['is_crew'];
                    }
                }
            }
        }

        return self::$cache[$convoId];
    }

    public function getConvosWithSingleParticipant() : array
    {
        $queryStr = 'SELECT conversation_id, COUNT(user_id) AS active_participants '.
                    'FROM participants WHERE conversation_id != 1 '.
                    'GROUP BY conversation_id HAVING active_participants = 1';

        $convoIds = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($rowData=$result->fetch_assoc()) != null)
                {
                    $convoIds[] = $rowData['conversation_id'];
                }
            }
        }

        return $convoIds;
    }

}

?>
