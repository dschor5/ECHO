<?php

class ParticipantsDao extends Dao
{
    private static $instance = null;

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

    public function canUserAccessConvo(int $convoId, int $userId) : bool
    {
        $result = false;
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';

        if(($result = $this->select('*', 'conversation_id='.$qConvoId.' AND user_id='.$qUserId)))
        {
            $result = ($result->num_rows == 1);
        }
        return $result;
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

    public function getParticipantIds(int $convoId, int $excludeUserId = -1) : array
    {
        $queryStr = 'SELECT participants.user_id, users.is_crew '.
                    'FROM participants '.
                    'JOIN users ON users.user_id=participants.user_id '. 
                    'WHERE participants.conversation_id=\''.$this->database->prepareStatement($convoId).'\' ';
    
        if($excludeUserId > 0)
        {
            $queryStr .= 'AND participants.user_id!=\''.$this->database->prepareStatement($excludeUserId).'\'';
        }
        
        $participants = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($rowData=$result->fetch_assoc()) != null)
                {
                    $participants[$rowData['user_id']] = $rowData['is_crew'];
                }
            }
        }

        return $participants;
    }

}

?>
