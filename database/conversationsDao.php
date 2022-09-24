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
     * @return Delay object
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
        parent::__construct('conversations');
    }

    /**
     * Used when creating a new user to grant them access to all the global conversations. 
     * 
     * @return List of all global conversations. 
     **/
    public function getGlobalConvos()
    {
        $convos = array();
        if(($result = $this->select('conversation_id, parent_conversation_id', 'conversation_id=\'1\' OR parent_conversation_id=\'1\'')) !== false)
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
     * @return array Converation objects.
     */
    public function getConversations($userId = null)
    {
        $qWhere = '';
        if($userId != null)
        {
            $qUserId = '\''.$this->database->prepareStatement($userId).'\'';
            $qWhere  = 'WHERE conversations.conversation_id IN ( '.
                            'SELECT participants.conversation_id FROM participants '.
                            'WHERE participants.user_id='.$qUserId.' ) ';
        }

        $queryStr = 'SELECT conversations.*, '.
                    'GROUP_CONCAT( participants.user_id) AS participants_id, '.
                    'GROUP_CONCAT( users.username) AS participants_username, '.
                    'GROUP_CONCAT( users.alias) AS participants_alias, '.
                    'GROUP_CONCAT( users.is_crew) AS participants_is_crew, '.
                        'COUNT(DISTINCT users.is_crew) AS participants_both_sites '.
                    'FROM conversations '.
                    'JOIN participants ON conversations.conversation_id = participants.conversation_id '.
                    'JOIN users ON users.user_id=participants.user_id '.
                    $qWhere.
                    'GROUP BY conversations.conversation_id ORDER BY conversations.conversation_id';

        $conversations = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($conversationData=$result->fetch_assoc()) != null)
                {
                    $currConversation = new Conversation($conversationData);
                    $conversations[$conversationData['conversation_id']] = $currConversation;
                }

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
     * 
     * Assumes that the calling function already checked that the 
     * thread name was valid and unique.
     *
     * @param Conversation $convo parent conversation.
     * @return int|false Conversation id or false on error. 
     */
    public function newThread(Conversation &$convo, string $threadName)
    {
        $this->startTransaction();

        // Create new conversation
        $currTime = new DelayTime();
        $convoFields = array(
            'name'                   => $threadName,
            'parent_conversation_id' => $convo->conversation_id,
            'date_created'           => $currTime->getTime(),
        );
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
        $keys = array('conversation_id', 'user_id');
        $participantsDao->insertMultiple($keys, $participantsFields);

        $this->endTransaction();

        return $convoId;
    }    

    /**
     * Get all new threads for a given conversation.
     *
     * @param array $convoIds Conversation ids to exclude in search
     * @param integer $userId Current user id
     * @return array of Conversation objects
     */
    public function getNewThreads(array $convoIds, int $userId) : array
    {
        $qConvoIds = implode(',',$convoIds);
        $qUserId   = '\''.$this->database->prepareStatement($userId).'\'';

        $queryStr = 'SELECT conversations.*, '.
            'GROUP_CONCAT( participants.user_id) AS participants_id, '.
            'GROUP_CONCAT( users.username) AS participants_username, '.
            'GROUP_CONCAT( users.alias) AS participants_alias, '.
            'GROUP_CONCAT( users.is_crew) AS participants_is_crew, '.
                'COUNT(DISTINCT users.is_crew) AS participants_both_sites '.
            'FROM conversations '.
            'JOIN participants ON conversations.conversation_id = participants.conversation_id '.
            'JOIN users ON users.user_id=participants.user_id '.
            'WHERE conversations.conversation_id NOT IN ('.$qConvoIds.') '. 
                'AND users.user_id='.$qUserId.' '.
            'GROUP BY conversations.conversation_id ORDER BY conversations.conversation_id';
        
        $conversations = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                // Retreieve results from query
                while(($conversationData=$result->fetch_assoc()) != null)
                {
                    $currConversation = new Conversation($conversationData);
                    $conversations[$conversationData['conversation_id']] = $currConversation;
                }

                foreach($conversations as $convoId => $convo)
                {
                    // Update parent with new thread id reference
                    self::$cache[$convo->parent_conversation_id]->addThreadId($convoId);

                    // Update cache
                    self::$cache[$convoId] = $convo;
                }
            }
        }

        return $conversations;
    }    
}

?>
