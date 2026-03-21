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
     * @return MessagesDao
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
     * Decrypt message text using the conversation's encryption key
     *
     * @param array $messageData Message data from database
     * @return array Message data with decrypted text
     */
    private function decryptMessageData(array $messageData): array
    {
        if (empty($messageData['text'])) {
            return $messageData;
        }

        // Get conversation encryption key
        $conversationsDao = ConversationsDao::getInstance();
        $conversationResult = $conversationsDao->select('encryption_key', 'conversation_id = ' . $messageData['conversation_id']);
        if ($conversationResult === false || $conversationResult->num_rows === 0) {
            Logger::warning('Conversation not found for message decryption', ['conversation_id' => $messageData['conversation_id']]);
            return $messageData;
        }

        $conversationData = $conversationResult->fetch_assoc();
        if (empty($conversationData['encryption_key'])) {
            // No encryption key - message might be unencrypted
            return $messageData;
        }

        try {
            $conversation = new Conversation($conversationData);
            $encryptionKey = $conversation->getEncryptionKey();
            if ($encryptionKey !== null) {
                $messageData['text'] = Encryption::decryptData($messageData['text'], $encryptionKey);
            }
        } catch (Exception $e) {
            Logger::error('Failed to decrypt message', [
                'message_id' => $messageData['message_id'],
                'conversation_id' => $messageData['conversation_id'],
                'error' => $e->getMessage()
            ]);
        }

        return $messageData;
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
        $tblMessages = $this->tableName('messages');

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
            $updateQueryStr = 'UPDATE `'.$tblMessages.'` messages SET messages.message_id_alt=@id_hab:=@id_hab+1 '. 
                'WHERE messages.conversation_id IN ('.$qConvoIds.') AND messages.from_crew=1 ';
                'ORDER BY IF(messages.from_crew, messages.recv_time_hab, messages.recv_time_mcc) ASC';
            $this->database->query($updateQueryStr);
            
            // Update id for messages from the perspective of mcc
            $updateQueryStr = 'UPDATE `'.$tblMessages.'` messages SET messages.message_id_alt=@id_mcc:=@id_mcc+1 '. 
                'WHERE messages.conversation_id IN ('.$qConvoIds.') AND messages.from_crew=0 ';
                'ORDER BY IF(messages.from_crew, messages.recv_time_hab, messages.recv_time_mcc) ASC';
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
        $tblMessages = $this->tableName('messages');

        $id = null;
        $ret = true;

        // Query exceptions are used to avoid too many levels of nested if-statements.
        $this->database->queryExceptionEnabled(true);
        try 
        {
            $this->startTransaction();
            
            $recvSource = $user->is_crew ? 'recv_time_hab' : 'recv_time_mcc';

            // Get conversation for encryption key
            $conversationResult = $conversationsDao->select('*', 'conversation_id = ' . $msgData['conversation_id']);
            if ($conversationResult === false || $conversationResult->num_rows === 0) {
                Logger::error('Conversation not found for message', ['conversation_id' => $msgData['conversation_id']]);
                $this->endTransaction(false);
                return false;
            }
            $conversationData = $conversationResult->fetch_assoc();
            $conversation = new Conversation($conversationData);

            $encryptionKey = $conversation->getEncryptionKey();
            if ($encryptionKey === null) {
                // Generate key for existing conversations that don't have one
                $conversation->generateEncryptionKey();
                $conversationsDao->update(['encryption_key' => $conversation->encryption_key], $conversation->conversation_id);
                $encryptionKey = $conversation->getEncryptionKey();
            }

            // Encrypt the message text if it's not empty
            $originalText = $msgData['text'];
            if (!empty($originalText)) {
                $msgData['text'] = Encryption::encryptData($originalText, $encryptionKey);
            }

            // Check if the message exists (has the same sender and content within the last 10 sec)
            // Note: This check will now fail for encrypted messages, but that's acceptable
            // as encryption makes duplicate detection less reliable anyway
            $queryStr = 'SELECT message_id FROM `'.$tblMessages.'` '.
                        'WHERE user_id='.$user->user_id.' '.
                        'AND conversation_id="'.$this->database->prepareStatement($msgData['conversation_id']).'" '.
                        'AND text="'.$this->database->prepareStatement($msgData['text']).'" '.
                        'AND message_type="'.$this->database->prepareStatement($msgData['message_type']).'" '.
                        'AND '.$recvSource.' > DATE_SUB(UTC_TIMESTAMP(3), INTERVAL 3 SECOND) '.
                        'ORDER BY message_id DESC LIMIT 1';
            if(($result = $this->database->query($queryStr)) !== false && $result->num_rows > 0)
            {
                $duplicate_id = $result->fetch_assoc()['message_id'];
                Logger::info('Duplicate message to message_id = '.$duplicate_id);
                $this->endTransaction();
                return $duplicate_id;
            }

            // Define query to find the next alternate id to assign to the new message
            $idQueryStr = 'SELECT @id_alt := COALESCE(MAX(message_id_alt),0) FROM `'.$tblMessages.'` '. 
                'WHERE conversation_id="'.$this->database->prepareStatement($msgData['conversation_id']).'" '. 
                'AND from_crew='.(($user->is_crew)?'1':'0');
            $this->database->query($idQueryStr);

            $habDelay = 0.0;
            $mccDelay = 0.0;
            if($user->is_crew)
            {
                $mccDelay = Delay::getInstance()->getDelay();
            }
            else
            {
                $habDelay = Delay::getInstance()->getDelay();
            }

            $mccDelayDays = floor($mccDelay / Delay::SEC_PER_DAY);
            $mccDelay = date('H:i:s', floor($mccDelay)).sprintf('.%03d', $mccDelay - floor($mccDelay));
            
            $habDelayDays = floor($habDelay / Delay::SEC_PER_DAY);
            $habDelay = date('H:i:s', floor($habDelay)).sprintf('.%03d', $habDelay - floor($habDelay));

            // Insert the new message into the database and automatically assign it 
            // an alternate id based on the previous query.
            $variables = array(
                'message_id_alt' => '@id_alt:=@id_alt+1', 
                'recv_time_hab' => 'ADDTIME(ADDDATE(UTC_TIMESTAMP(3), '.$habDelayDays.'), \''.$habDelay.'\')',
                'recv_time_mcc' => 'ADDTIME(ADDDATE(UTC_TIMESTAMP(3), '.$mccDelayDays.'), \''.$mccDelay.'\')',
            );
            $id = $this->insert($msgData, $variables);

            // If the message was successfully added to the database, then 
            // proceed to create entries in other tables that need to reference
            // the newly created message id.
            if ($id !== false)
            {
                $this->database->query('SET @msg_id = LAST_INSERT_ID();');

                // Add file attachments if any.
                if(count($fileData) > 0)
                {
                    $fileData['message_id'] = '@msg_id';
                    unset($fileData['message_id']);
                    $msgFileDao->insert($fileData, array('message_id'=>'@msg_id'));
                }

                // Create message status entries for the new entry.
                $participants = $participantsDao->getParticipantIds($msgData['conversation_id']);
                $msgStatusData = array();
                $msgStatusVariables = array();
                foreach($participants as $userId => $isCrew)
                {
                    $msgStatusData[] = array(
                        'user_id' => $userId
                    );
                }
                $msgStatusVariables = array(
                    'message_id' => '@msg_id'
                );
                $ret = $messageStatusDao->insertMultiple($msgStatusData, $msgStatusVariables);

                // Update the date the conversation was last updated.
                $conversationsDao->convoUpdated($msgData['conversation_id']);
                $this->endTransaction();
            }
            else
            {
                // If the message was not created retract the database query.
                $id = false;
                $this->endTransaction(false);
            }
        }
        catch(Exception $e)
        {
            // If the message was not created retract the database query.
            $id = false;
            $this->endTransaction(false);
            Logger::warning('messagesDao::sendMessage failed.', [$e->getMessage()]);
        }
        $this->database->queryExceptionEnabled(false);

        return $id;
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
                $msgStatusDao->insertMultiple($msgStatus);
            }
        }
    }   

    /**
     * Get the last message id within a specific conversation. 
     *
     * @param array $convoIds
     * @param integer $userId
     * @param boolean $isCrew
     * @return integer
     */
    public function getLastMessageId(array $convoIds, int $userId, bool $isCrew) : int
    {
        // Build query
        $qConvoIds = implode(',',$convoIds);
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $tblMessages = $this->tableName('messages');

        $queryStr = 'SELECT messages.message_id '. 
                        // 'users.username, users.alias, users.is_active, '.
                        // 'msg_files.original_name, msg_files.server_name, msg_files.mime_type '.
                    'FROM `'.$tblMessages.'` messages '.
                    // 'JOIN users ON users.user_id=messages.user_id '.
                    // 'LEFT JOIN msg_status ON messages.message_id=msg_status.message_id '.
                    //     'AND msg_status.user_id='.$qUserId.' '.
                    // 'LEFT JOIN msg_files ON messages.message_id=msg_files.message_id '.
                    'WHERE messages.conversation_id IN ('.$qConvoIds.') '.
                        'AND messages.'.$qRefTime.' <= UTC_TIMESTAMP(3) '.
                    'ORDER BY messages.'.$qRefTime.' DESC, messages.message_id DESC '.
                    'LIMIT 1, 1';

        $this->startTransaction();

        $messageId = -1;

        // Get all messages
        if(($result = $this->database->query($queryStr)) !== false && $result->num_rows == 1)
        {
            if($result->num_rows == 1)
            {
                $rowData = $result->fetch_assoc();
                $messageId = $rowData['message_id'];
            }
        }

        $this->endTransaction();

        return $messageId;
    }

    /**
     * Get messages lost when a stream is disconnected.
     *
     * @param array $convoIds Conversation ids to include in the query. 
     *                        If threads are disabled, the query can get all the messages
     *                        in the conversation and its subthreads.
     * @param integer $userId Checks msg_status for this user
     * @param boolean $isCrew Used to select receive time perspective
     * @param integer $lastId ID of last message successfully sent
     * @param integer $offset Offset if trying to get lots of messages
     * @return array Array of Message objects
     */
    public function getMissedMessages(array $convoIds, int $userId, bool $isCrew, int $lastId, int $offset=0) : array
    {
        // Build query
        $qConvoIds = implode(',',$convoIds);
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $qLastId  = intval($lastId);
        $tblMessages = $this->tableName('messages');
        $tblUsers = $this->tableName('users');
        $tblMsgStatus = $this->tableName('msg_status');
        $tblMsgFiles = $this->tableName('msg_files');
        $tblMsgSaved = $this->tableName('msg_saved');

        $queryStr = 'SELECT messages.*, '. 
                        'users.username, users.alias, users.is_active, '.
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type, '.
                        'IF(msg_saved.message_id IS NULL, 0, 1) AS is_saved '.
                    'FROM `'.$tblMessages.'` messages '.
                    'JOIN `'.$tblUsers.'` users ON users.user_id=messages.user_id '.
                    'LEFT JOIN `'.$tblMsgStatus.'` msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'LEFT JOIN `'.$tblMsgFiles.'` msg_files ON messages.message_id=msg_files.message_id '.
                    'LEFT JOIN `'.$tblMsgSaved.'` msg_saved ON messages.message_id=msg_saved.message_id '.
                        'AND msg_saved.user_id='.$qUserId.' ' .
                    'WHERE messages.conversation_id IN ('.$qConvoIds.') '.
                        'AND messages.message_id > '.$qLastId.' '.
                        'AND messages.'.$qRefTime.' <= UTC_TIMESTAMP(3) '.
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
                    $decryptedData = $this->decryptMessageData($rowData);
                    $messages[$rowData['message_id']] = new Message($decryptedData);
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
     * Get a specific message. 
     *
     * @param int $messageId Message to retrieve
     * @param int $userId Checks msg_status for this user and if the message is saved for this user
     * @return Message object
     */
    public function getLastMessage(int $messageId, int $userId) : ?Message
    {
        // Build query
        $qMessageId  = '\''.$this->database->prepareStatement($messageId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $tblMessages = $this->tableName('messages');
        $tblUsers = $this->tableName('users');
        $tblMsgFiles = $this->tableName('msg_files');
        $tblMsgSaved = $this->tableName('msg_saved');
        
        $queryStr = 'SELECT messages.*, '. 
                        'users.username, users.alias, users.is_active, '.
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type, '.
                        'IF(msg_saved.message_id IS NULL, 0, 1) AS is_saved '.
                    'FROM `'.$tblMessages.'` messages '.
                    'JOIN `'.$tblUsers.'` users ON users.user_id=messages.user_id '.
                    'LEFT JOIN `'.$tblMsgFiles.'` msg_files ON messages.message_id=msg_files.message_id '.
                    'LEFT JOIN `'.$tblMsgSaved.'` msg_saved ON messages.message_id=msg_saved.message_id '.
                        'AND msg_saved.user_id='.$qUserId.' ' .
                    'WHERE messages.message_id='.$qMessageId;
                    
        $message = false;
        
        // Get all messages
        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0) 
            {
                $rowData = $result->fetch_assoc();
                $decryptedData = $this->decryptMessageData($rowData);
                $message = new Message($decryptedData);
            }
        }
        
        return $message;
    }

    /**
     * Get new messages.
     *
     * @param array $convoIds Conversation ids to include in the query. 
     *                        If threads are disabled, the query can get all the messages
     *                        in the conversation and its subthreads.
     * @param integer $userId Checks msg_status for this user
     * @param boolean $isCrew Used to select receive time perspective
     * @param integer $offset Offset if trying to get lots of messages
     * @return array Array of Message objects
     */
    public function getNewMessages(array $convoIds, int $userId, bool $isCrew, int $offset=0) : array
    {
        // Build query
        $qConvoIds = implode(',',$convoIds);
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qOffset  = $this->database->prepareStatement($offset);
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $tblMessages = $this->tableName('messages');
        $tblUsers = $this->tableName('users');
        $tblMsgStatus = $this->tableName('msg_status');
        $tblMsgFiles = $this->tableName('msg_files');
        $tblMsgSaved = $this->tableName('msg_saved');
        
        $this->database->query('SET @ts := UTC_TIMESTAMP(3);');

        $queryStr = 'SELECT messages.*, '. 
                        'users.username, users.alias, users.is_active, '.
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type, '.
                        'IF(msg_saved.message_id IS NULL, 0, 1) AS is_saved '.
                    'FROM `'.$tblMessages.'` messages '.
                    'JOIN `'.$tblUsers.'` users ON users.user_id=messages.user_id '.
                    'LEFT JOIN `'.$tblMsgStatus.'` msg_status ON messages.message_id=msg_status.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '.
                    'LEFT JOIN `'.$tblMsgFiles.'` msg_files ON messages.message_id=msg_files.message_id '.
                    'LEFT JOIN `'.$tblMsgSaved.'` msg_saved ON messages.message_id=msg_saved.message_id '.
                        'AND msg_saved.user_id='.$qUserId.' '.
                    'WHERE messages.conversation_id IN ('.$qConvoIds.') '.
                        'AND msg_status.message_id IS NOT NULL '.    
                        'AND messages.'.$qRefTime.' <= @ts '.
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
                    $decryptedData = $this->decryptMessageData($rowData);
                    $messages[$rowData['message_id']] = new Message($decryptedData);
                }
            }
        }
        
        // Update message read status 
        if(count($messages) > 0)
        {
            $maxMsgId = max(array_keys($messages));
            $delStr   = 'DELETE msg_status '.
            'FROM `'.$tblMsgStatus.'` msg_status '.
            'JOIN `'.$tblMessages.'` messages ON messages.message_id=msg_status.message_id '.
            'WHERE msg_status.user_id='.$qUserId.' '.
                'AND messages.conversation_id IN ('.$qConvoIds.') '. 
                'AND messages.'.$qRefTime.' <= @ts '; 
                //'AND msg_status.message_id <= '.$maxMsgId;

            $this->database->query($delStr);
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
        $tblMessages = $this->tableName('messages');
        $tblUsers = $this->tableName('users');
        $tblMsgFiles = $this->tableName('msg_files');
        $tblMsgSaved = $this->tableName('msg_saved');
        $tblMsgStatus = $this->tableName('msg_status');

        $queryStr = 'SELECT messages.*, '. 
                        'users.username, users.alias, users.is_active, '.
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type, '.
                        'IF(msg_saved.message_id IS NULL, 0, 1) AS is_saved ' . 
                    'FROM `'.$tblMessages.'` messages '.
                    'JOIN `'.$tblUsers.'` users ON users.user_id=messages.user_id '.
                    'LEFT JOIN `'.$tblMsgFiles.'` msg_files ON messages.message_id=msg_files.message_id ' .
                    'LEFT JOIN `'.$tblMsgSaved.'` msg_saved ON messages.message_id=msg_saved.message_id ' . 
                        'AND msg_saved.user_id='.$qUserId.' ' .
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
                        $decryptedData = $this->decryptMessageData($rowData);
                        $messages[$rowData['message_id']] = new Message($decryptedData);
                    }
                }
            }
            
            if(count($messages) > 0)
            {
                $maxMsgId = max(array_keys($messages));
                $delStr   = 'DELETE msg_status '.
                'FROM `'.$tblMsgStatus.'` msg_status '.
                'JOIN `'.$tblMessages.'` messages ON messages.message_id=msg_status.message_id '.
                'WHERE msg_status.user_id='.$qUserId.' '.
                    'AND messages.conversation_id IN ('.$qConvoIds.') '. 
                    'AND messages.'.$qRefTime.' <= '.$qToDate.' ';
                    //'AND msg_status.message_id <= '.$maxMsgId;

                $this->database->query($delStr);
            }
            $this->endTransaction();

            
        } 
        catch (Exception $e) 
        {
            $this->endTransaction(false);
            Logger::warning('messagesDao::getOldMessages failed.', [$e->getMessage()]);
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
     * @return array
     */
    public function getMsgNotifications(int $conversationId, int $userId, bool $isCrew)
    {
        $notifications = array();

        $qConvoId = '\''.$this->database->prepareStatement($conversationId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $qRefTime = $isCrew ? 'recv_time_hab' : 'recv_time_mcc';
        $tblMessages = $this->tableName('messages');
        $tblMsgStatus = $this->tableName('msg_status');

        // Build query that counts new new messages and number of important messages. 
        // We leave it to the applicaiton to determine if the number changed from the
        // last time the query was ran or not. 
        $queryStr = 'SELECT messages.conversation_id, '. 
                        'COUNT(*) AS num_new, '. 
                        "SUM(IF(messages.message_type = 'important', 1, 0)) AS num_important ".
                    'FROM `'.$tblMessages.'` messages, `'.$tblMsgStatus.'` msg_status '.
                    'WHERE messages.conversation_id<>'.$qConvoId.' '. 
                        'AND msg_status.message_id=messages.message_id '.
                        'AND msg_status.user_id='.$qUserId.' '. 
                        'AND messages.'.$qRefTime.' <= UTC_TIMESTAMP(3) '. 
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
        $tblMessages = $this->tableName('messages');
        $tblConversations = $this->tableName('conversations');

        $this->startTransaction();

        // Delete all messags
        $this->database->query('DELETE FROM `'.$tblMessages.'`');

        // Reset message counter
        $this->database->query('ALTER TABLE `'.$tblMessages.'` AUTO_INCREMENT = 1');

        // Delete all threads
        $this->database->query('DELETE FROM `'.$tblConversations.'` WHERE parent_conversation_id IS NOT NULL');

        // Update date for date created and last message.
        $this->database->query('UPDATE `'.$tblConversations.'` SET date_created=UTC_TIMESTAMP(3), last_message=UTC_TIMESTAMP(3)');
       
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
        $tblMessages = $this->tableName('messages');
        $tblMsgFiles = $this->tableName('msg_files');
        $tblMsgSaved = $this->tableName('msg_saved');
        
        // Build query
        $queryStr = 'SELECT messages.*, '. 
                        'msg_files.original_name, msg_files.server_name, msg_files.mime_type, '.
                        'IF(msg_saved.message_id IS NULL, 0, 1) AS is_saved '.
                    'FROM `'.$tblMessages.'` messages '.
                    'LEFT JOIN `'.$tblMsgFiles.'` msg_files ON messages.message_id=msg_files.message_id '.
                    'LEFT JOIN `'.$tblMsgSaved.'` msg_saved ON messages.message_id=msg_saved.message_id '. 
                        'AND msg_saved.user_id='.$qUserId.' ' .
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
                    $decryptedData = $this->decryptMessageData($rowData);
                    $messages[$rowData['message_id']] = new Message($decryptedData);
                }
            }
        }
    
        return $messages;
    }

    /**
     * Count all messages containing a file attachment in the given conversations.
     *
     * @param array $convo_ids
     * @return integer
     */
    public function countMessagesInConvo(array $convo_ids) : int 
    {
        $tblMsgFiles = $this->tableName('msg_files');
        $tblMessages = $this->tableName('messages');
        $queryStr = 'SELECT count(*) as num_files FROM `'.$tblMsgFiles.'` msg_files '. 
                    'JOIN `'.$tblMessages.'` messages ON messages.message_id=msg_files.message_id '. 
                    'WHERE messages.conversation_id IN ('.join(',', $convo_ids).');';

        $numMsgs = 0;

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                $numMsgs = $result->fetch_assoc()['num_files'];
            }
        }

        return $numMsgs;
    }

}

?>
