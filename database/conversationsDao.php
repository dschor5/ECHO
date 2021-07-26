<?php

class ConversationsDao extends Dao
{
    private static $instance = null;
    private static $cache = array();

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new ConversationsDao();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        parent::__construct('conversations');
    }

    public function getById(int $id = null)
    {
        $conversation = null;

        if(isset(self::$cache[$id]))
        {
            $conversation = self::$cache[$id];
        }

        if($conversation == null)
        {
            if (($result = $this->select('*','conversation_id=\''.$this->database->prepareStatement($id).'\'')) !== false)
            {
                if ($result->num_rows > 0)
                {
                    $conversationData = $result->fetch_assoc();
                    self::$cache[$conversationData['conversation_id']] = new Conversation($conversationData);
                    $conversation = self::$cache[$conversationData['conversation_id']];
                }
            }
        }

        return $conversation;
    }

    public function getConversationsByUserId(int $userId, string $sort='conversation_id', $order='ASC')
    {
        $queryStr = 'SELECT conversations.*, '.
                        'GROUP_CONCAT(DISTINCT participants.user_id) AS conversation_participants, '.
                        'GROUP_CONCAT(DISTINCT users.username) AS conversation_usernames '.
                    'FROM conversations '.
                    'JOIN participants ON conversations.conversation_id = participants.conversation_id '.
                    'JOIN users ON users.user_id=participants.user_id '.
                    'WHERE conversations.conversation_id IN ( '.
                        'SELECT participants.conversation_id '.
                        'FROM participants '.
                        'WHERE participants.user_id=\''.$this->database->prepareStatement($userId).'\' ) '.
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
