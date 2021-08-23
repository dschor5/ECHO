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

    public function getConversationsByUserId(int $userId)
    {
        $qUserId = '\''.$this->database->prepareStatement($userId).'\'';

        $queryStr = 'SELECT conversations.*, '.
                        'GROUP_CONCAT( participants.user_id) AS participant_ids, '.
                        'GROUP_CONCAT( users.username) AS participant_usernames, '.
                        'GROUP_CONCAT( users.alias) AS participants_aliases, '.
                        'COUNT(DISTINCT users.is_crew) AS participants_both_sites '
                    'FROM conversations '.
                    'JOIN participants ON conversations.conversation_id = participants.conversation_id '.
                    'JOIN users ON users.user_id=participants.user_id '.
                    'WHERE conversations.conversation_id IN ( '.
                        'SELECT participants.conversation_id FROM participants '.
                        'WHERE participants.user_id='.$qUserId.' ) '.
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
