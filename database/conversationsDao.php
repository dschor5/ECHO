<?php

/**
 * Data Abstraction Object for the Conversations table. Implements custom 
 * queries to search and update conversations as needed. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class ConversationsDao extends Dao
{
    /**
     * Singleton instance for ConversationsDao object.
     * @access private
     * @var ConversationsDao
     **/
    private static $instance = null;

    /**
     * Cache to avoid multiple queries for conversation data. 
     * @access private
     * @var array 
     **/
    private static $cache = array();

    /**
     * Returns singleton instance of this object. 
     * 
     * @return ConversationsDao
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new ConversationsDao();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instances of this object.
     **/
    protected function __construct()
    {
        parent::__construct('conversations', 'conversation_id');
    }

    /**
     * Override insert to generate encryption key for new conversations
     *
     * @param array $fields
     * @param array $variables
     * @return int|bool
     */
    public function insert(array $fields, array $variables = array())
    {
        // Generate encryption key if not provided
        if (!isset($fields['encryption_key'])) {
            $conversation = new Conversation(array());
            $conversation->generateEncryptionKey();
            $fields['encryption_key'] = $conversation->encryption_key;
        }

        return parent::insert($fields, $variables);
    }

    /**
     * Used when creating a new user to grant them access to all the global conversations. 
     * By default, only the Mission Chat (conversation_id=1) and all its threads
     * (parent_conversation_id=1) are the only global/public conversations. 
     * 
     * @return array of all global conversations ids. No objects returned.
     **/
    public function getGlobalConvos() : array 
    {
        $convos = array();
        if(($result = $this->select('conversation_id, parent_conversation_id', 
            'conversation_id=\'1\' OR parent_conversation_id=\'1\'')) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($data=$result->fetch_assoc()) != null)  
                {
                    $convos[$data['conversation_id']] = $data['conversation_id'];
                    if($data['parent_conversation_id'] != null)
                    {
                        $convos[$data['parent_conversation_id']] = $data['parent_conversation_id'];
                    }
                }
            }
        }
        return $convos;
    }

    /**
     * Get all conversations. If provided, only get those for the particular userId.
     *
     * Implementation notes:
     * - For each conversations, include a CSV list of user_ids, usernames, and alias, 
     *   as well as an indicator of whether the convo includes participants on both 
     *   the analog and MCC. These fields are expected by the Conversation object to 
     *   avoid having to perfom separate queries. 
     *
     * @param int $userId (optional)
     * @param bool $includePrivate (optional) include private convos by default.
     * @return array Converation objects.
     */
    public function getConversations($userId = null, bool $includePrivate = true)
    {
        $qWhere = array();
        $tblConversations = $this->tableName('conversations');
        $tblParticipants = $this->tableName('participants');
        $tblUsers = $this->tableName('users');

        // Additional clause if userId is provided.
        if($userId != null)
        {
            $qUserId = '\''.$this->database->prepareStatement($userId).'\'';
            $qWhere[]  = 'conversations.conversation_id IN ( '.
                            'SELECT participants.conversation_id FROM `'.$tblParticipants.'` participants '.
                            'WHERE participants.user_id='.$qUserId.' ) ';
        }

        // Additional clause if private conversations are to be included.
        if(!$includePrivate)
        {
            $convos = $this->getGlobalConvos();
            $qWhere[] = 'conversations.conversation_id IN ('.join(', ', $convos).') ';
        }

        // Combine all WHERE clauses together. 
        $qWhere = (count($qWhere) > 0) ? 'WHERE '.join(' AND ', $qWhere) : '';

        $queryStr = 'SELECT conversations.*, '.
                    'GROUP_CONCAT( participants.user_id) AS participants_id, '.
                    'GROUP_CONCAT( users.username) AS participants_username, '.
                    'GROUP_CONCAT( users.alias) AS participants_alias, '.
                    'GROUP_CONCAT( users.is_crew) AS participants_is_crew, '.
                    'GROUP_CONCAT( users.is_active) AS participants_is_active, '.
                    'COUNT(DISTINCT users.is_crew) AS participants_both_sites '.
                    'FROM `'.$tblConversations.'` conversations '.
                    'JOIN `'.$tblParticipants.'` participants ON conversations.conversation_id = participants.conversation_id '.
                    'JOIN `'.$tblUsers.'` users ON users.user_id=participants.user_id '.
                    $qWhere.
                    'GROUP BY conversations.conversation_id ORDER BY conversations.conversation_id';

        $conversations = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                // Get all conversations returned by the query.
                while(($conversationData=$result->fetch_assoc()) != null)
                {
                    $currConversation = new Conversation($conversationData);
                    $conversations[$conversationData['conversation_id']] = $currConversation;
                }

                // Iterate through all the objects and create links for any conversation threads.
                foreach($conversations as $convoId => $convo)
                {
                    if($convo->parent_conversation_id != null)
                    {
                        $conversations[$convo->parent_conversation_id]->addThreadId($convoId);
                    }
                }
            }

            self::$cache = $conversations;
        }

        return $conversations;
    }

    /**
     * Create a new thread for the given conversation.
     * This is a two step operation:
     * - Add the new conversation
     * - Add all the participants to the new conversation. 
     *   Note that this could have been avoided as this info is 
     *   essentially duplicated from the parent. However, that 
     *   added to the complexity of the implementation. 
     * 
     * Assumes that the calling function already checked that the 
     * thread name was valid and unique.
     *
     * @param Conversation $convo parent conversation.
     * @param string $threadName 
     * @return int|false Conversation id or false on error. 
     */
    public function newThread(Conversation &$convo, string $threadName)
    {
        // Create new conversation
        $currTime = new DelayTime();
        $convoFields = array(
            'name'                   => $threadName,
            'parent_conversation_id' => $convo->conversation_id,
            'date_created'           => $currTime->getTime(),
            'last_message_mcc'       => $currTime->getTime(),
            'last_message_hab'       => $currTime->getTime(),
        );

        $this->startTransaction();

        $convoId = $this->insert($convoFields);

        if($convoId === false)
        {
            Logger::error('Cannot create new thread for '.$convo->conversation_id.'.');
            $this->endTransaction(false);
            return false;
        }

        // Copy over the participants from teh parent conversation to the thread
        $participantsDao = ParticipantsDao::getInstance();
        $participants = explode(',', $convo->participants_id);
        $participantsFields = array();
        foreach($participants as $userId)
        {
            $participantsFields[] = array(
                'conversation_id' => $convoId,
                'user_id' => $userId,
            );
        }
        $participantsDao->insertMultiple($participantsFields);

        $this->endTransaction();

        return $convoId;
    }    

    /**
     * Get all new conversations and threads for a given conversation.
     *
     * @param array $convoIds Conversation ids to exclude in search
     * @param integer $userId Current user id
     * @return array of Conversation objects
     */
    public function getNewConversations(array $convoIds, int $userId) : array
    {
        $qConvoIds = implode(',',$convoIds);

        $qUserId = '\''.$this->database->prepareStatement($userId).'\'';
        $tblConversations = $this->tableName('conversations');
        $tblParticipants = $this->tableName('participants');
        $tblUsers = $this->tableName('users');

        $queryStr = 'SELECT conversations.*, '.
            'GROUP_CONCAT( participants.user_id) AS participants_id, '.
            'GROUP_CONCAT( users.username) AS participants_username, '.
            'GROUP_CONCAT( users.alias) AS participants_alias, '.
            'GROUP_CONCAT( users.is_crew) AS participants_is_crew, '.
            'GROUP_CONCAT( users.is_active) AS participants_is_active, '.
            'COUNT(DISTINCT users.is_crew) AS participants_both_sites '.
            'FROM `'.$tblConversations.'` conversations '.
            'JOIN `'.$tblParticipants.'` participants ON conversations.conversation_id = participants.conversation_id '.
            'JOIN `'.$tblUsers.'` users ON users.user_id=participants.user_id '.
            'WHERE conversations.conversation_id NOT IN ('.$qConvoIds.') '. 
                'AND conversations.conversation_id IN ( '.
                    'SELECT participants.conversation_id FROM `'.$tblParticipants.'` participants '.
                    'WHERE participants.user_id='.$qUserId.' ) '.
            'GROUP BY conversations.conversation_id ORDER BY conversations.conversation_id';
        
        $conversations = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                // Retreieve results from query
                while(($conversationData=$result->fetch_assoc()) != null)
                {
                    $conversations[$conversationData['conversation_id']] = 
                        new Conversation($conversationData);
                }

                // Link all the threads for each conversation
                foreach($conversations as $convoId => $convo)
                {
                    if($convo->parent_conversation_id != null)
                    {
                        // Update parent with new thread id reference
                        self::$cache[$convo->parent_conversation_id]->addThreadId($convoId);
                    }

                    // Update cache
                    self::$cache[$convoId] = $convo;
                }
            }
        }

        return $conversations;
    }    

    /**
     * Change the date when the conversation was last updated. 
     *
     * @param integer $convoId
     * @return bool
     */
    public function convoUpdated(int $convoId) : bool 
    {
        $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';
        $tblConversations = $this->tableName('conversations');
        $tblMessages = $this->tableName('messages');

        $queryStr = 'UPDATE `'.$tblConversations.'` c
            LEFT JOIN (
                SELECT conversation_id, MAX(recv_time_mcc) AS last_mcc, MAX(recv_time_hab) AS last_hab
                FROM `'.$tblMessages.'` messages
                GROUP BY conversation_id
            ) m ON c.conversation_id = m.conversation_id
            SET 
                c.last_message_mcc = m.last_mcc,
                c.last_message_hab = m.last_hab
            WHERE c.conversation_id = '.$qConvoId;
                
        return ($this->database->query($queryStr) !== false);
    }
}

?>
