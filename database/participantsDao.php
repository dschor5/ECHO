<?php

class ParticipantsDao extends Dao
{
    private static $instance = null;
    private static $cache = array();

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new ParticipantsDao();
        }
        return self::$instance;
    }

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

}

?>
