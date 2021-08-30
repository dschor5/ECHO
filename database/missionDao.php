<?php

class MissionDao extends Dao
{
    private static $instance = null;
    private static $cache = null;

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MissionDao();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        parent::__construct('mission_config');
    }

    public function readMissionConfig() : array
    {
        if(self::$cache == null)
        {
            if(($result = $this->select('*','*')) !== false)
            {   
                if($result->num_rows > 0)
                {
                    // Override cache
                    self::$cache = array();
                    while(($data = $result->fetch_assoc()) != null)
                    {
                        self::$cache[$data['name']] = array(
                                'type'  => $data['type'],
                                'value' => $data['value']
                            );
                    }
                }
            }
        }

        return self::$cache;
    }

    public function saveMissionConfig() : bool
    {
        return true;
    }
}

?>