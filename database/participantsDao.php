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

    public function getParticipantIds(int $convoId, int $excludeUserId) : array
    {
        $queryStr = 'SELECT participants.user_id, users.is_crew '.
                    'FROM participants '.
                    'JOIN users ON users.user_id=participants.user_id '. 
                    'WHERE participants.conversation_id=\''.$this->database->prepareStatement($convoId).
                        '\' AND participants.user_id!=\''.$this->database->prepareStatement($excludeUserId).'\'';
        
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
