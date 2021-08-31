<?php

class MissionConfig
{
    private static $instance = null;
    private $data = array();

    private function __construct()
    {
        $missionDao = MissionDao::getInstance();
        $this->data = $missionDao->readMissionConfig();
    }

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MissionConfig();
        }
        return self::$instance;
    }

    public function __get(string $name)
    {
        $result = null;

        var_dump($this->data);

        if(array_key_exists($name, $this->data))
        {
            switch($this->data[$name]['type'])
            {
                case 'int': 
                    $result = intval($this->data[$name]['value']); 
                    break;
                case 'float':
                    $result = floatval($this->data[$name]['value']); 
                    break;
                case 'bool':
                    $result = boolval($this->data[$name]['value']); 
                    break;
                default:
                    $result = strval($this->data[$name]['value']);
            }
        }
        else
        {
            throw new Exception();
        }

        return $result;
    }
}


?>