<?php

class MissionDao extends Dao
{
    private static $instance = null;

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
        $missionData = array();
        if(($result = $this->select('*','*')) !== false)
        {   
            if($result->num_rows > 0)
            {
                while(($data = $result->fetch_assoc()) != null)
                {
                    $missionData[$data['name']] = array(
                            'type'  => $data['type'],
                            'value' => $data['value']
                        );
                }
            }
        }

        return $missionData;
    }

    public function updateMissionConfig(array $data) : bool
    {
        $queryStr = 'UPDATE mission_config SET value = ( CASE ';
        $qIn = array();
        foreach($data as $name => $value) 
        {
            $qName = '\''.$this->database->prepareStatement($name).'\'';
            $qIn[] = $qName;
            $qValue = '\''.$this->database->prepareStatement($value).'\'';
            $queryStr .= 'WHEN name='.$qName.' THEN '.$qValue.' ';
        }
        $queryStr .= 'END) WHERE name IN ('.implode(',', $qIn).')';

        return ($this->database->query($queryStr) !== false);
    }
}

?>