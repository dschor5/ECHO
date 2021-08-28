<?php

class Mission 
{
    private static $instance = null;
    private $data = array();

    private function __construct(array $data)
    {
        $missionDao = MissionDao::getInstance();
        $this->data = $missionDao->loadSettings();
    }

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new Mission();
        }
        return self::$instance;
    }

    public function __get(string $name) : mixed
    {
        $result = null;

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