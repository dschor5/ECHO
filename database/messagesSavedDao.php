<?php

/**
 * Data Abstraction Object for the msg_saved table. Implements custom 
 * queries to search and update conversations as needed. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class MessagesSavedDao extends Dao
{
    /**
     * Singleton instance for MessagesSavedDao object.
     * @access private
     * @var MessagesSavedDao
     **/
    private static $instance = null;

    /**
     * Returns singleton instance of this object. 
     * 
     * @return MessagesSavedDao
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MessagesSavedDao();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instances of this object.
     **/
    protected function __construct()
    {
        parent::__construct('msg_saved');
    }
}

?>
