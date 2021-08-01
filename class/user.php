<?php

require_once('database/usersDao.php');

class User
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getId(): int
    {
        return $this->data['user_id'];
    }

    public function getUsername(): string
    {
        return $this->data['username'];
    }

    public function getAlias(): string
    {
        if(strlen($this->data['alias']) == 0)
        {
            return $this->getUsername();
        }
        return $this->data['alias'];
    }

    public function getLocation(): string
    {
        global $mission;
        $location = $mission['home_planet'];
        if($this->isCrew())
        {
            $location = $mission['away_planet'];
        }
        return $location;
    }

    public function isAdmin():bool
    {
        return ($this->data['is_admin'] == 1);
    }

    public function isCrew(): bool
    {
        return ($this->data['is_crew'] == 1);
    }

    public function isResetPasswordRequired() : bool
    {
        return ($this->data['password_reset'] == 1);
    }

    public function isValidPassword(string $password): bool
    {
        return (md5($password) == $this->data['password']);
    }

    public function createNewSession()
    {
        $ret = false;
        $usersDao = UsersDao::getInstance();

        $newData = array(
            'session_id' => dechex(rand(0,time())).dechex(rand(0,time())).dechex(rand(0,time())),
            'last_login' => date('Y-m-d H:i:s', time())
        );

        if ($usersDao->update($newData, $this->data['user_id']) !== false)
        {
            $ret = $newData['session_id'];
        }

        return $ret;
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