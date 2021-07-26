<?php

class MessagesDao extends Dao
{
    public function __construct(&$database)
    {
        parent::__construct($database,'Messages');
    }

    public function getMessages()
    {
        return null;
    }

    
}

?>
