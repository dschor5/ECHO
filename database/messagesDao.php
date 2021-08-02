<?php

class MessagesDao extends Dao
{
    private static $instance = null;

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MessagesDao();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        parent::__construct('messages');
    }

    public function newUserAccessToPrevMessages(int $convoId, int $userId)
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $msgStatusDao = MessageStatusDao::getInstance();
        
        // TODO - Inneficient if there are a large number of messages. 
        //        Would need to get count and use that to break up the 
        //        request into batches. 
        if (($result = $this->select('message_id', 'conversation_id='.$qConvoId)) !== false)
        {
            if ($result->num_rows > 0)
            {
                $msgStatus = array();
                while(($msgData = $result->fetch_assoc()) != null)
                {
                    $msgStatus[] = array(
                        'message_id' => $msgData['message_id'],
                        'user_id' => $userId,
                        'is_read' => 0
                    );
                }
                $msgStatusDao->insertMultiple($msgStatus);
            }
        }
    }

    // Get messages for user not yet delivered
    //SELECT messages.*, users.is_crew, msg_status.is_delivered FROM messages JOIN users ON users.user_id = messages.user_id JOIN msg_status ON messages.message_id=msg_status.message_id AND msg_status.user_id = 2 WHERE messages.recv_time_mcc <= '2021-07-31 23:59:00'

    // Get messages for user already delivered 
    // Need to add ORDER BY received time, OFFSET/LIMIT
    // Get messages in chronological order ORDER BY messages.recv_time_mcc DESC LIMIT 0, 25
    // Create/update link offset each time it is pressed
    public function updateReadFlag(int $convoId, int $userId, bool $isCrew, string $date)
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qDate    = '\''.$this->database->prepareStatement($date).'\'';

        $queryStr = 'UPDATE msg_status '.
                    'JOIN messages ON msg_status.message_id=messages.message_id '.
                    'SET msg_status.is_read=1 '. 
                    'WHERE msg_status.user_id='.$qUserId.' '.
                        'AND messages.conversation_id='.$qConvoId.' '. 
                        'AND messages.'.$qRefTime.' <= '.$qDate;

        $messagesChanged = 0;
        if($this->database->query($queryStr) !== false)
        {
            $messagesChanged = $this->database->getNumRowsAffected();
        }

        return $messagesChanged;
    }

    public function getMessagesReceived(int $convoId, int $userId, bool $isCrew, string $toDate, int $offset=0, string $fromDate='0000-00-00 00:00:00') : array
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qFromDate = '\''.$this->database->prepareStatement($fromDate).'\'';
        $qToDate   = '\''.$this->database->prepareStatement($toDate).'\'';

        $queryStr = 'SELECT messages.*, users.username, users.alias, users.is_crew, msg_status.is_read '.
                    'FROM messages '.
                    'JOIN users ON users.user_id=messages.user_id '.
                    'JOIN msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'WHERE messages.conversation_id='.$qConvoId.' '.
                        'AND messages.'.$qRefTime.' > '.$qFromDate.' '.   
                        'AND messages.'.$qRefTime.' <= '.$qToDate.' '.
                        'AND msg_status.is_read=\'1\' '.
                    'ORDER BY messages.'.$qRefTime.' '.
                    'LIMIT '.$qOffset.', 25';
        
        $messages = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($rowData=$result->fetch_assoc()) != null)
                {
                    $messages[$rowData['message_id']] = new Message($rowData);
                }
            }
        }

        return $messages;
    }

    /*public function getMessagesReceived(int $convoId, int $userId, bool $isCrew, string $date, int $offset=0) : array
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qDate    = '\''.$this->database->prepareStatement($date).'\'';

        $queryStr = 'SELECT messages.*, users.username, users.alias, users.is_crew, msg_status.is_read '.
                    'FROM messages '.
                    'JOIN users ON users.user_id=messages.user_id '.
                    'JOIN msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'WHERE messages.conversation_id='.$qConvoId.' '.
                           'AND messages.'.$qRefTime.' <= '.$qDate.' '.
                    'ORDER BY messages.'.$qRefTime.' '.
                    'LIMIT '.$qOffset.', 25';
        
        $messages = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($rowData=$result->fetch_assoc()) != null)
                {
                    $messages[$rowData['message_id']] = new Message($rowData);
                }
            }
        }

        return $messages;
    }*/
    

}

?>
