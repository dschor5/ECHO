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

    public function getConversationList()
    {
        return $this->data['conversations'];
    }
}

?>