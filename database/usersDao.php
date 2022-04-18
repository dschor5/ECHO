<?php

class UsersDao extends Dao
{
    /**
     * Singleton instance for UsersDao object.
     * @access private
     * @var ConversationsDao
     **/
    private static $instance = null;

    /**
     * Cache users retrieved from database into an associative array 
     * arranged by user id. 
     * @access private
     * @var array 
     **/
    private static $cache = array();


    private static $cacheFull = false;

    /**
     * Returns singleton instance of this object. 
     * 
     * @return Delay object
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new UsersDao();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instances of this object.
     **/
    protected function __construct()
    {
        parent::__construct('users', 'user_id');
    }


    public function getById(int $id)
    {
        $user = null;

        if(isset(self::$cache[$id]))
        {
            $user = self::$cache[$id];
        }

        if($user == null)
        {
            $queryStr = 'SELECT users.*, ('. 
                    'SELECT GROUP_CONCAT(participants.conversation_id) FROM participants '. 
                    'WHERE participants.user_id=users.user_id) AS conversations '. 
                'FROM users WHERE users.user_id='.$id;

            if (($result = $this->database->query($queryStr)) !== false)
            {
                if ($result->num_rows > 0)
                {
                    $userData = $result->fetch_assoc();
                    self::$cache[$userData['user_id']] = new User($userData);
                    $user = self::$cache[$userData['user_id']];
                }
            }
        }
        return $user;
    }

    public function getByUsername(string $username)
    {
        $user = false;

        foreach(self::$cache as $userId => $cachedUser)
        {
            if(strcmp($cachedUser->username, $username) === 0)
            {
                $user = $cachedUser;
                break;
            }
        }

        if($user === false)
        {
            $qUsername = '\''.$this->database->prepareStatement($username).'\'';

            $queryStr = 'SELECT users.*, ('. 
                    'SELECT GROUP_CONCAT(participants.conversation_id) FROM participants '. 
                    'WHERE participants.user_id=users.user_id) AS conversations '. 
                'FROM users WHERE users.username='.$qUsername;

            if (($result = $this->database->query($queryStr)) !== false)
            {
                if ($result->num_rows > 0)
                {
                    $userData = $result->fetch_assoc();
                    self::$cache[$userData['user_id']] = new User($userData);
                    $user = self::$cache[$userData['user_id']];
                }
            }
        }

        return $user;
    }

    public function getUsers(string $sort='user_id', string $order='ASC'): array
    {
        if(self::$cacheFull === false)
        {
            if(($result = $this->select('*','*',$sort,$order)) !== false)
            {   
                if($result->num_rows > 0)
                {
                    // Override cache
                    self::$cache = array();
                    while(($data = $result->fetch_assoc()) != null)
                    {
                        self::$cache[$data['user_id']] = new User($data);
                    }
                    self::$cacheFull = true;
                }
            }
        }

        return self::$cache;
    }

    public function createNewUser($fields)
    {
        $result = false;
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $messagesDao = MessagesDao::getInstance();
        
        $this->database->queryExceptionEnabled(true);

        try
        {
            $this->startTransaction();
            $newUserId = $this->insert($fields);
            

            $users = $this->getUsers();

            // Give the new user access to all the previous mission messges
            $convos = $conversationsDao->getGlobalConvos();
            
            $newParticipants = array();
            foreach($convos as $convoId)
            {
                $newParticipants[] = array(
                    'conversation_id' => $convoId,
                    'user_id' => $newUserId,
                );
                $messagesDao->newUserAccessToPrevMessages($convoId, $newUserId);
            }
            $participantsDao->insertMultiple($newParticipants);

            foreach($users as $otherUserId=>$user)
            {
                if($newUserId != $user->user_id)
                {
                    $newConvoData = array(
                        'name' => $user->alias.'-'.$users[$newUserId]->alias,
                        'parent_conversation_id' => null,
                    );
                    $newConvoId = $conversationsDao->insert($newConvoData);

                    $newParticipants = array(
                        array(
                            'conversation_id' => $newConvoId,
                            'user_id' => $newUserId,
                        ),
                        array(
                            'conversation_id' => $newConvoId,
                            'user_id' => $otherUserId,
                        ),
                    );
                    $participantsDao->insertMultiple($newParticipants);
                }
            }
            $this->endTransaction(true);
            $result = true;
        }
        catch(Exception $e)
        {
            $this->endTransaction(false);
            Logger::warning('usersDao::createNewUser', $e);
        }

        $this->database->queryExceptionEnabled(false);

        return $result;
    }    

    public function deleteUser($userId)
    {
        $result = false;
        $participantsDao = ParticipantsDao::getInstance();
        $conversationsDao = ConversationsDao::getInstance();

        $this->database->queryExceptionEnabled(true);
        try 
        {
            $this->startTransaction();
            $this->drop($userId);
            $convosToDelete = $participantsDao->getConvosWithSingleParticipant();

            if(count($convosToDelete) > 0)
            {
                $conversationsDao->drop('conversation_id IN ('.implode(',', $convosToDelete).')');
            }
            $this->endTransaction();
            $result = true;
        }
        catch(Exception $e)
        {
            $this->endTransaction(false);
            Logger::warning('usersDao::deleteUser', $e);
        }
        $this->database->queryExceptionEnabled(false);

        return $result;
    }
}

?>
