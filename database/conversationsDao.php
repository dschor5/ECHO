<?php

class ConversationsDao extends Dao
{
    private static $instance = null;
    private static $cache = array();
    private static $cacheFull = false;

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

    public function getConversationById(int $id = null)
    {
        return null;
    }    
}

?>
