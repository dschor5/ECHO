<?php

class MessageFileDao extends Dao
{
    private static $instance = null;

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MessageFileDao();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        parent::__construct('msg_files');
    }

    public function getFile(string $serverName, int $userId)
    {
        $qServerName = '\''.$this->database->prepareStatement($serverName).'\'';
        $qUserId = '\''.$this->database->prepareStatement($userId).'\'';
        
        $queryStr = 'SELECT msg_files.* FROM msg_files '.
                    'JOIN messages ON messages.message_id=msg_files.message_id '.
                    'JOIN participants ON participants.conversation_id=messages.conversation_id '.
                    'WHERE msg_files.server_name='.$qServerName.' AND participants.user_id='.$qUserId;

        $file = null;

        if(($result = $this->database->query($queryStr)) !== false)
        {
            if($result->num_rows > 0)
            {
                $file = new FileUpload($result->fetch_assoc());
            }
        }

        return $file;
    }

}

?>
