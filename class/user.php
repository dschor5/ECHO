<?php

require_once('database/usersDao.php');

class User
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
        if(isset($data['conversations']))
        {
            $this->data['conversations'] = explode(',', $this->data['conversations']);
        }
        else
        {
            $this->data['conversations'] = array();
        }
    }

    public function __get($name)
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $pos = strpos($this->data[$name], 'is_');
            if($pos !== false && $pos == 0)
            {
                $result = ($this->data[$name] == 1);
            }
            else
            {
                $result = $this->data[$name];
            }
        }

        return $result;
    }

    public function getLocation(): string
    {
        $mission = MissionConfig::getInstance();
        $location = $mission->mcc_planet;
        if($this->is_crew)
        {
            $location = $mission->hab_planet;
        }
        return $location;
    }

    public function isValidPassword(string $password): bool
    {
        return (md5($password) == $this->data['password']);
    }

    public function createNewSession()
    {
        $this->data['session_id'] = dechex(rand(0,time())).dechex(rand(0,time())).dechex(rand(0,time()));
        return $this->data['session_id'];
    }

    public function isValidSession($cmpKey)
    {
        $valid=false;
        if ($cmpKey == $this->data['session_id'])
        {
            $valid=true;
        }

        return $valid;
    }
}

?>