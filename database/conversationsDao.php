<?php

/**
 * Data Abstraction Object for the Conversations table. Implements custom 
 * queries to search and update conversations as needed. 
 * 
 * Implementation Notes:
 * - 
 * 
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
                    self::$cache[$conversationData['conversation_id']] = $currConversation;
                }
            }
        }

        return $conversations;
    }
}

?>
