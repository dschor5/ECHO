<?php

class TopicsDao extends Dao
{
    public function __construct(&$database)
    {
        parent::__construct($database,'Topics');
    }
}

?>
