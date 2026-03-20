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

    /**
     * If the message is currently saved, this function will unsaved it. 
     * If the message is not saved, this function will save it.
     *
     * @param integer $userId
     * @param integer $messageId
     * @return boolean True if message is now saved, false if message is now unsaved.
     */
    public function toggleSaveMessage(int $userId, int $messageId) : bool 
    {
        $ret = false;

        $qMsgId = '\''.$this->database->prepareStatement($messageId).'\'';
        $qUserId  = '\''.$this->database->prepareStatement($userId).'\'';
        $tblMsgSaved = $this->tableName('msg_saved');

        $deleteQuery = 'DELETE FROM `'.$tblMsgSaved.'` '. 
                           'WHERE user_id='.$qUserId.' '. 
                           'AND message_id='.$qMsgId;
        $this->database->query($deleteQuery);
        if($this->database->getNumRowsAffected() == 0) 
        {
            $insertQuery = 'INSERT INTO `'.$tblMsgSaved.'` (user_id, message_id) '. 
                           'VALUES ('.$qUserId.', '.$qMsgId.')';
            $this->database->query($insertQuery);
            $ret = true;
        }

        return $ret;
    }
}

?>
