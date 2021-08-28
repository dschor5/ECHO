<?php

class MissionDao extends Dao
{
    private static $instance = null;
    private static $cache = array();

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new ParticipantsDao();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        parent::__construct('participants');
    }