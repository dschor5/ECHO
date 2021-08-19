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

    public function sendMessage(array $msgData, array $fileData=array())
    {
        $messageStatusDao = MessageStatusDao::getInstance();
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $msgFileDao = MessageFileDao::getInstance();

        $this->database->enableQueryException();
        try 
        {
            $this->startTransaction();
            $messageId = $this->insert($msgData);
            if($messageId !== false)
            {
                if(count($fileData) > 0)
                {
                    $fileData['message_id'] = $messageId;
                    $msgFileDao->insert($fileData);
                }

                $participants = $participantsDao->getParticipantIds($msgData['conversation_id']);
                $msgStatusData = array();
                foreach($participants as $userId => $isCrew)
                {
                    $msgStatusData[] = array(
                        'message_id' => $messageId,
                        'user_id' => $userId,
                        'is_read' => 0,
                    );
                }
                $messageStatusDao->insertMultiple($msgStatusData);
                $conversationsDao->update(array('last_message'=>$msgData['sent_time']), 'conversation_id='.$msgData['conversation_id']);
                $this->endTransaction();
            }
            else
            {
                $messageId = false;
                $this->endTransaction(false);
            }
        }
        catch(Exception $e)
        {
            $messageId = false;
            $this->endTransaction(false);
        }
        $this->database->disableQueryException();

        return $messageId;
    }



    public function newUserAccessToPrevMessages(int $convoId, int $userId)
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
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

    public function getNewMessages(int $convoId, int $userId, bool $isCrew, string $toDate, int $offset=0) : array
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qToDate   = 'CAST(\''.$this->database->prepareStatement($toDate).'\' AS DATETIME)';
        $qFromDate = 'SUBTIME(CAST('.$qToDate.' AS DATETIME), \'00:00:05\')';

        $queryStr = 'SELECT messages.*, users.username, users.alias, users.is_crew, msg_status.is_read '.
                    'FROM messages '.
                    'JOIN users ON users.user_id=messages.user_id '.
                    'JOIN msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'LEFT JOIN msg_files ON messages.message_id=msg_files.message_id '.
                    'WHERE messages.conversation_id='.$qConvoId.' '.
                        'AND msg_status.is_read=0 '.    
                        'AND (messages.'.$qRefTime.' BETWEEN '.$qFromDate.' AND '.$qToDate.') '.
                    'ORDER BY messages.'.$qRefTime.' '.
                    'LIMIT '.$qOffset.', 25';
        
        $messages = array();

    
        $this->startTransaction();

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
        
        if(count($messages) > 0)
        {
            $messageIds = '('.implode(', ', array_keys($messages)).')';
            $messageStatusDao = MessageStatusDao::getInstance();
            $messageStatusDao->update(array('is_read'=>'1'), 'user_id='.$qUserId.' AND message_id IN '.$messageIds);
        }

        $participantsDao = ParticipantsDao::getInstance();
        $participantsDao->updateLastRead($convoId, $userId, $toDate);
        
        $this->endTransaction();
        
        return $messages;
    }

    public function getMessagesReceived(int $convoId, int $userId, bool $isCrew, string $toDate, int $offset=0) : array
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qToDate   = '\''.$this->database->prepareStatement($toDate).'\'';

        
        $queryStr = 'SELECT messages.*, users.username, users.alias, users.is_crew, msg_status.is_read '.
                    'FROM messages '.
                    'JOIN users ON users.user_id=messages.user_id '.
                    'JOIN msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'LEFT JOIN msg_files ON messages.message_id=msg_files.message_id '.
                    'WHERE messages.conversation_id='.$qConvoId.' '.
                        'AND messages.'.$qRefTime.' <= '.$qToDate.' '.
                    'ORDER BY messages.'.$qRefTime.' '.
                    'LIMIT '.$qOffset.', 25';
        
        $messages = array();

        $this->database->enableQueryException();
        try
        {
            $this->startTransaction();

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
            
            $queryStr = 'UPDATE msg_status '.
                'JOIN messages ON msg_status.message_id=messages.message_id '.
                'SET msg_status.is_read=1 '. 
                'WHERE msg_status.user_id='.$qUserId.' '.
                    'AND messages.conversation_id='.$qConvoId.' '. 
                    'AND messages.'.$qRefTime.' <= '.$qToDate;

            $this->database->query($queryStr);
            $this->endTransaction();

            
        } 
        catch (Exception $e) 
        {
            $this->endTransaction(false);
        }
        $this->database->disableQueryException();

        return $messages;
    }

    

}

?>
