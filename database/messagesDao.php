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

    // Get messages for user not yet delivered
    //SELECT messages.*, users.is_crew, msg_status.is_delivered FROM messages JOIN users ON users.user_id = messages.user_id JOIN msg_status ON messages.message_id=msg_status.message_id AND msg_status.user_id = 2 WHERE messages.recv_time_mcc <= '2021-07-31 23:59:00'

    // Get messages for user already delivered 
    // Need to add ORDER BY received time, OFFSET/LIMIT
    // Get messages in chronological order ORDER BY messages.recv_time_mcc DESC LIMIT 0, 25
    // Create/update link offset each time it is pressed
    

    public function getMessagesReceived(int $convoId, int $userId) : array
    {
        $queryStr = 'SELECT messages.*, users.username, users.alias, users.is_crew, msg_status.is_delivered '.
                    'FROM messages '.
                    'JOIN users ON users.user_id = messages.user_id '.
                    'JOIN msg_status ON messages.message_id = msg_status.message_id '.
                        'AND msg_status.user_id = 2 '.
                    'WHERE messages.conversation_id=1, messages.recv_time_mcc <= "2021-07-31 23:59:00" AND msg_status.is_delivered=1 '.
                    'ORDER BY messages.recv_time_mcc '.
                    'LIMIT '.$offset.', 25';

        $messages = array();

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
    


    //SELECT messages.*, users.is_crew FROM messages JOIN users ON users.user_id = messages.user_id WHERE messages.recv_time_mcc <= '2021-07-30 23:59:00'

}

?>
