<?php

/**
 * Data Abstraction Object for the messages table. Implements custom 
 * queries to search and update conversations as needed. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class MessagesDao extends Dao
{
    /**
     * Singleton instance for MessageDao object.
     * @access private
     * @var MessagesDao
     **/
    private static $instance = null;

    /**
     * Returns singleton instance of this object. 
     * 
     * @return Delay object
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MessagesDao();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instances of this object.
     **/
    protected function __construct()
    {
        parent::__construct('messages', 'message_id');
    }

    /**
     * Renumber alternate message ids based on whether threads are enabled or not.
     *
     * @param boolean $threadsEnabled
     * @return void
     */
    public function renumberSiteMessageId(bool $threadsEnabled)
    {
        $this->startTransaction();

        $conversationsDao = ConversationsDao::getInstance();
        $conversations = $conversationsDao->getConversations();

        foreach($conversations as $convoId => $convo) 
        {
            // If threads are disabled, then apply renumbering to the parent 
            // conversation and all the threads by combining their ids. 
            // And skip all the threads as they would have been caught by 
            // the parent conversation.
            if($threadsEnabled == false)
            {
                if($convo->parent_conversation_id == null)
                {
                    $convoIds = array_merge(array($convoId), $convo->thread_ids);
                }
                else
                {
                    continue;
                }
            }
            // Otherwise, if therads are enable, renumber each one individually.
            else
            {
                $convoIds = array($convoId);
            }

            $qConvoIds = implode(',',$convoIds);

            // Initialize internal mysql variables.
            $idQueryStr = 'SET @id_hab := 0, @id_mcc := 0;';
            $this->database->query($idQueryStr);
            
            // Update id for messages from the perspective of the habitat
            $updateQueryStr = 'UPDATE messages SET messages.message_id_alt=@id_hab:=@id_hab+1 '. 
                'WHERE messages.conversation_id IN ('.$qConvoIds.') AND messages.from_crew=1 ';
                'ORDER BY messages.sent_time ASC';
            $this->database->query($updateQueryStr);
            
            // Update id for messages from the perspective of mcc
            $updateQueryStr = 'UPDATE messages SET messages.message_id_alt=@id_mcc:=@id_mcc+1 '. 
                'WHERE messages.conversation_id IN ('.$qConvoIds.') AND messages.from_crew=0 ';
                'ORDER BY messages.sent_time ASC';
            $this->database->query($updateQueryStr);
        }        

        Logger::info('MessagesDao::renumberSiteMessageId() complete.');
        $this->endTransaction();
    }

    /**
     * Write new message to the database. 
     *
     * @param array $msgData Associative array with message fields. 
     * @param array $fileData Associative array with file attachment fields.
     * @return int|bool New message id on success. False otherwise. 
     **/
    public function sendMessage(User &$user, array $msgData, array $fileData=array())
    {
        $messageStatusDao = MessageStatusDao::getInstance();
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $msgFileDao = MessageFileDao::getInstance();

        $ids = array('message_id' => null, 'message_id_alt' => null);

        // Query exceptions are used to avoid too many levels of nested if-statements.
        $this->database->queryExceptionEnabled(true);
        try 
        {
            $this->startTransaction();
            
            // Define query to find the next alternate id to assign to the new message
            $idQueryStr = 'SELECT @id_alt := COALESCE(MAX(message_id_alt),0) FROM messages '. 
                'WHERE conversation_id="'.$this->database->prepareStatement($msgData['conversation_id']).'" '. 
                'AND from_crew='.(($user->is_crew)?'1':'0');
            $this->database->query($idQueryStr);

            // Insert the new message into the database and automatically assign it 
            // an alternate id based on the previous query.
            $variables = array('message_id_alt' => '@id_alt:=@id_alt+1');
            $ids['message_id'] = $this->insert($msgData, $variables);

            // If the message was successfully added to the database, then 
            // proceed to create entries in other tables that need to reference
            // the newly created message id.
            if ($ids['message_id'] !== false)
            {
                // Add file attachments if any.
                if(count($fileData) > 0)
                {
                    $fileData['message_id'] = $ids['message_id'] ;
                    $msgFileDao->insert($fileData);
                }

                // Create message status entries for the new entry.
                $participants = $participantsDao->getParticipantIds($msgData['conversation_id']);
                $msgStatusData = array();
                foreach($participants as $userId => $isCrew)
                {
                    if($user->user_id != $userId)
                    {
                        $msgStatusData[] = array(
                            'message_id' => $ids['message_id'] ,
                            'user_id' => $userId
                        );
                    }
                }
                $keys = array('message_id', 'user_id');
                $messageStatusDao->insertMultiple($keys, $msgStatusData);

                // Update the date the conversation was last updated.
                $conversationsDao->update(array('last_message'=>$msgData['sent_time']), 'conversation_id='.$msgData['conversation_id']);
                
                // Finally, run a query to get the data recently entered into the database. 
                if (($result = $this->select('*', $ids['message_id'] )) !== false)
                {
                    if ($result->num_rows > 0) 
                    {
                        if(($msgIdData = $result->fetch_assoc()) != null)
                        {
                            $ids['message_id_alt'] = $msgIdData['message_id_alt'];
                        }
                    }
                }               
                $this->endTransaction();
            }
            else
            {
                // If the message was not created retract the database query.
                $ids = false;
                $this->endTransaction(false);
            }
        }
        catch(Exception $e)
        {
            // If the message was not created retract the database query.
            $ids = false;
            $this->endTransaction(false);
            Logger::warning('messagesDao::sendMessage failed.', $e->getMessage());
        }
        $this->database->queryExceptionEnabled(false);

        return $ids;
    }

    /**
     * Grant new users access to previous messages on a given conversation.
     *
     * @param integer $convoId
     * @param integer $userId
     * @return void
     */
    public function newUserAccessToPrevMessages(int $convoId, int $userId)
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $msgStatusDao = MessageStatusDao::getInstance();
        
        // TODO - Inneficient if there are a large number of messages. 
        //        Would need to get count and use that to break up the 
        //        request into batches. 
        // Get a list of all message ids in a given conversation. 
        if (($result = $this->select('message_id', 'conversation_id='.$qConvoId)) !== false)
        {
            // Iterate through results and create new entries to the message status
            // table for the new user id.
            if ($result->num_rows > 0)
            {
                $msgStatus = array();
                while(($msgData = $result->fetch_assoc()) != null)
                {
                    $msgStatus[] = array(
                        'message_id' => $msgData['message_id'],
                        'user_id' => $userId,
                    );
                }
                $keys = array('message_id', 'user_id');
                $msgStatusDao->insertMultiple($keys, $msgStatus);
            }
        }
    }   

    /**
     * Get messages lost when a stream is disconnected.
     *
     * @param array $convoIds Conversation ids to include in the query. 
     *                        If threads are disabled, the query can get all the messages
     *                        in the conversation and its subthreads.
     * @param integer $userId Checks msg_status for this user
     * @param boolean $isCrew Used to select receive time perspective
     * @param string $toDate  Messages received before this date
     * @param integer $lastId ID of last message successfully sent
     * @param integer $offset Offset if trying to get lots of messages
     * @return array Array of Message objects
     */
    public function getMissedMessages(array $convoIds, int $userId, bool $isCrew, string $toDate, int $lastId, int $offset=0) : array
    {
        // Build query
        $qConvoIds = implode(',',$convoIds);
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qLastId  = intval($lastId);
        $qToDate   = 'CAST(\''.$this->database->prepareStatement($toDate).'\' AS DATETIME)';

        $queryStr = 'SELECT messages.*, '. 
                        'users.username, users.alias, '.
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type '.
                    'FROM messages '.
                    'JOIN users ON users.user_id=messages.user_id '.
                    'LEFT JOIN msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'LEFT JOIN msg_files ON messages.message_id=msg_files.message_id '.
                    'WHERE messages.conversation_id IN ('.$qConvoIds.') '.
                        'AND messages.message_id > '.$qLastId.' '.
                        'AND (messages.'.$qRefTime.' <= '.$qToDate.') '.
                    'ORDER BY messages.'.$qRefTime.' ASC, messages.message_id ASC '.
                    'LIMIT '.$qOffset.', 25';
        
        $messages = array();

    
        $this->startTransaction();

        // Get all messages
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
        
        // Update message read status 
        if(count($messages) > 0)
        {
            $messageIds = '('.implode(', ', array_keys($messages)).')';
            $messageStatusDao = MessageStatusDao::getInstance();
            $messageStatusDao->drop('user_id='.$qUserId.' AND message_id IN '.$messageIds);
        }
        
        $this->endTransaction();
        
        return $messages;
    }

    /**
     * Get new messages.
     *
     * @param array $convoIds Conversation ids to include in the query. 
     *                        If threads are disabled, the query can get all the messages
     *                        in the conversation and its subthreads.
     * @param integer $userId Checks msg_status for this user
     * @param boolean $isCrew Used to select receive time perspective
     * @param string $toDate  Messages received before this date
     * @param integer $offset Offset if trying to get lots of messages
     * @return array Array of Message objects
     */
    public function getNewMessages(array $convoIds, int $userId, bool $isCrew, string $toDate, int $offset=0) : array
    {
        // Build query
        $qConvoIds = implode(',',$convoIds);
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qToDate   = 'CAST(\''.$this->database->prepareStatement($toDate).'\' AS DATETIME)';

        $queryStr = 'SELECT messages.*, '. 
                        'users.username, users.alias, '.
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type '.
                    'FROM messages '.
                    'JOIN users ON users.user_id=messages.user_id '.
                    'LEFT JOIN msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'LEFT JOIN msg_files ON messages.message_id=msg_files.message_id '.
                    'WHERE messages.conversation_id IN ('.$qConvoIds.') '.
                        'AND msg_status.message_id IS NOT NULL '.    
                        'AND (messages.'.$qRefTime.' <= '.$qToDate.') '.
                    'ORDER BY messages.'.$qRefTime.' ASC, messages.message_id ASC '.
                    'LIMIT '.$qOffset.', 25';
        
        $messages = array();

    
        $this->startTransaction();

        // Get all messages
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
        
        // Update message read status 
        if(count($messages) > 0)
        {
            $messageIds = '('.implode(', ', array_keys($messages)).')';
            $messageStatusDao = MessageStatusDao::getInstance();
            $messageStatusDao->drop('user_id='.$qUserId.' AND message_id IN '.$messageIds);
        }
        
        $this->endTransaction();
        
        return $messages;
    }

    /**
     * Get old messages in a conversation. 
     *
     * @param array $convoIds    Conversation ids to include in the query. 
     *                           If threads are disabled, the query can get all the messages
     *                           in the conversation and its subthreads.
     * @param integer $userId    Checks msg_status for this user
     * @param boolean $isCrew    Used to select receive time perspective
     * @param string $toDate     Messages received before this date
     * @param int $lastMsgId     Last message id received
     * @param integer $numMsgs   Max number of messages to retrieve in query
     * @return array
     */
    public function getOldMessages(array $convoIds, int $userId, bool $isCrew, string $toDate, int $lastMsgId=PHP_INT_MAX, int $numMsgs=20) : array
    {
        $qConvoIds = implode(',',$convoIds);
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qlastMsgId  = '\''.$this->database->prepareStatement($lastMsgId).'\'';
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qToDate   = '\''.$this->database->prepareStatement($toDate).'\'';

        $queryStr = 'SELECT messages.*, '. 
                        'users.username, users.alias, '.
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type '.
                    'FROM messages '.
                    'JOIN users ON users.user_id=messages.user_id '.
                    'LEFT JOIN msg_files ON messages.message_id=msg_files.message_id '.
                    'WHERE messages.conversation_id IN ('.$qConvoIds.') '.
                        'AND messages.'.$qRefTime.' <= '.$qToDate.' '.
                        'AND messages.message_id < '.$qlastMsgId.' '.
                    'ORDER BY messages.'.$qRefTime.' DESC, messages.message_id DESC '.
                    'LIMIT 0, '.$numMsgs;
        
        $messages = array();

        $this->database->queryExceptionEnabled(true);
        try
        {
            $this->startTransaction();

            // Get old messages 
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
                $messageStatusDao->drop('user_id='.$qUserId.' AND message_id IN '.$messageIds);
            }
            $this->endTransaction();

            
        } 
        catch (Exception $e) 
        {
            $this->endTransaction(false);
            Logger::warning('messagesDao::getOldMessages failed.', $e);
        }
        $this->database->queryExceptionEnabled(false);

        return array_reverse($messages, true);
    }

    /**
     * Get new message notifications. These are the total number of new messages
     * on each conversation/thread and how many of those are flagged as important.
     *
     * @param integer $conversationId Conversation id to check.
     * @param integer $userId
     * @param boolean $isCrew
     * @param string $toDate
     * @return void
     */
    public function getMsgNotifications(int $conversationId, int $userId, bool $isCrew, string $toDate)
    {
        $notifications = array();

        $qConvoId = '\''.$this->database->prepareStatement($conversationId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qToDate   = '\''.$this->database->prepareStatement($toDate).'\'';

        // Build query that counts new new messages and number of important messages. 
        // We leave it to the applicaiton to determine if the number changed from the
        // last time the query was ran or not. 
        $queryStr = 'SELECT messages.conversation_id, '. 
                        'COUNT(*) AS num_new, '. 
                        "SUM(IF(messages.type = 'important', 1, 0)) AS num_important ".
                    'FROM messages '.
                    'JOIN msg_status ON messages.message_id=msg_status.message_id '. 
                    'WHERE messages.conversation_id<>'.$qConvoId.' '. 
                        'AND msg_status.message_id IS NOT NULL '.
                        'AND msg_status.user_id='.$qUserId.' '. 
                        'AND messages.'.$qRefTime.' <= '.$qToDate.' '. 
                    'GROUP BY messages.conversation_id '.
                    'ORDER BY messages.conversation_id';
        
        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($rowData=$result->fetch_assoc()) != null)
                {
                    $notifications[$rowData['conversation_id']] = array(
                        'num_new' => $rowData['num_new'], 
                        'num_important' => $rowData['num_important']
                    );
                }
            }
        }       

        return $notifications;
    }

    /**
     * Clear messages and threads to initialize database for new mission.
     *
     * @return void
     */
    public function clearMessagesAndThreads()
    {
        $conversationsDao = ConversationsDao::getInstance();

        $this->startTransaction();

        // Delete all messags
        $this->database->query('DELETE FROM messages');

        // Reset message counter
        $this->database->query('ALTER TABLE messages AUTO_INCREMENT = 1');

        // Delete all threads
        $this->database->query('DELETE FROM conversations WHERE parent_conversation_id IS NOT NULL');

        // Update date for date created and last message.
        $conversationsDao->update(
            array(
                'date_created' => '0000-00-00 00:00:00',
                'last_message' => '0000-00-00 00:00:00',
            )
        );
        $this->endTransaction();
    }

    /**
     * Get list of new messages for a particular conversation
     *
     * @param array $convoIds   Array of conversation ids to check
     * @param boolean $isCrew   Flag to select receive time for HAB or MCC
     * @param integer $offset   Offset for piecewise queries
     * @param integer $numMsgs  Number of messages per query
     * @return array Message objects
     */
    public function getMessagesForConvo(array $convoIds, bool $isCrew, int $offset, int $numMsgs) : array
    {
        $qConvoIds = implode(',',$convoIds);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        
        // Build query
        $queryStr = 'SELECT messages.*, '. 
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type '.
                    'FROM messages '.
                    'LEFT JOIN msg_files ON messages.message_id=msg_files.message_id '.
                    'WHERE messages.conversation_id IN ('.$qConvoIds.') '.
                    'ORDER BY messages.'.$qRefTime.' ASC, messages.message_id ASC '.
                    'LIMIT '.$offset.', '.$numMsgs;
        
        $messages = array();
       
        // Get all messages.
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

}

?>
