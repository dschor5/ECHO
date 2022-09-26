<?php

/**
 * Data Abstraction Object for the participants table. Implements custom 
 * queries to search and update conversations as needed. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class ParticipantsDao extends Dao
{
    /**
     * Singleton instance for ParticipantsDao object.
     * @access private
     * @var ConversationsDao
     **/
    private static $instance = null;

    /**
     * Cache participants queries into an associative array arranged by conversation id. 
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
            self::$instance = new ParticipantsDao();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instances of this object.
     **/
    protected function __construct()
    {
        parent::__construct('participants');
    }

    /** 
     * Get an associative list of all the particpant ids and their crew/mcc status 
     * that belong to a particular conversation. 
     *
     * Implementation notes:
     * - Cache results to prevent executing the same query multiple times. 
     * 
     * @param int $convoId 
     * @return array(user_id=>is_crew)
     **/
    public function getParticipantIds(int $convoId) : array
    {
        if(!isset(self::$cache[$convoId]))
        {
            $qConvoId = '\''.$this->database->prepareStatement($convoId).'\'';

            $queryStr = 'SELECT participants.user_id, users.is_crew '.
                        'FROM participants '.
                        'JOIN users ON users.user_id=participants.user_id '. 
                        'WHERE participants.conversation_id='.$qConvoId;
            
            self::$cache[$convoId] = array();

            if(($result = $this->database->query($queryStr)) !== false)
            {
                if($result->num_rows > 0)
                {
                    while(($rowData=$result->fetch_assoc()) != null)
                    {
                        self::$cache[$convoId][$rowData['user_id']] = $rowData['is_crew'];
                    }
                }
            }
        }

        return self::$cache[$convoId];
    }

    /**
     * Get an array of converation ids containing a single participants. 
     * This is used when deleting users to help cleanup orphan conversations. 
     *
     * @return array of conversation ids to delete.
     **/
    public function getConvosWithSingleParticipant() : array
    {
        // Query to get all conversations that excludes conversation_id=1 (mission chat) and its threads.
        $queryStr = 'SELECT participants.conversation_id, COUNT(participants.user_id) AS active_participants '.
                    'FROM participants '. 
                    'JOIN conversations ON participants.conversation_id=conversations.conversation_id '.
                    'WHERE conversations.conversation_id != 1 AND '. 
                        '(conversations.parent_conversation_id IS NULL OR conversations.parent_conversation_id != 1) '.
                    'GROUP BY participants.conversation_id HAVING active_participants = 1';

        $convoIds = array();

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                while(($rowData=$result->fetch_assoc()) != null)
                {
                    $convoIds[] = $rowData['conversation_id'];
                }
            }
        }

        return $convoIds;
    }

}

?>
