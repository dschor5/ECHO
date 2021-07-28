<?php

class MessageStatusDao extends Dao
{
    private static $instance = null;

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MessageStatusDao();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        parent::__construct('msg_status');
    }
    
}

?>
