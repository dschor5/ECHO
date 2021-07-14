<?php

require_once('database/usersDao.php');

class User
{
    private $db;
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getUsername(): string
    {
        return $this->data['username'];
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
        return $this->data['password_reset'] == 1;
    }

    public function isValidPassword(string $password): bool
    {
        return md5($password) == $this->data['password'];
    }

    public function createNewSession()
    {
        $ret = false;
        $usersDao = UsersDao::getInstance();

        $sessionId = dechex(rand(0,time())).dechex(rand(0,time())).dechex(rand(0,time()));

        if ($usersDao->update(array('session_id'=>$sessionId,'last_login'=>date('Y-m-d H:i:s', time())),$this->data['id']) !== false)
            $ret = $sessionId;

        return $ret;
    }

    public function isValidSession($cmpKey)
    {
        $valid=false;
        if ($cmpKey == $this->data['session_id'])
        {
            $valid=true;
            //$this->setLastLogin();
        }

        return $valid;
    }

}

?>