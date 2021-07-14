<?php

require_once("database/dao.php");
require_once("class/user.php");
require_once("database/result.php");

class UsersDao extends Dao
{
    private static $instance = null;
    private static $cache = array();

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new UsersDao();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        parent::__construct('users');
    }

    public function getByUsername(string $username)
    {
        $user = null;

        foreach(self::$cache as $cachedUser)
        {
            if(strcmp($cachedUser->getUsername(), $username) === 0)
            {
                $user = $cachedUser;
                break;
            }
        }

        if($user == null)
        {
            $ret = null;
            $result = $this->select('*','username=\''.$this->database->prepareStatement($username).'\'');
            if ($result != null)
            {
                if ($result->getRowCount() > 0)
                {
                    $userData = $result->getRow();
                    self::$cache[$userData['id']] = new User($userData);
                    $user = self::$cache[$userData['id']];
                }
            }
        }

        return $user;
    }

    public function getByUsedId(int $id): User
    {
        $ret = false;
        if ($id != 0 && ($result=$this->select('*',$id)) !== false)
            if ($result->getRowCount() > 0)
                $ret = new User($result->getRow());
        return $ret;
    }

    public function getUsers(int $mission_id, string $sort='name', string $order='ASC')
    {
        $users = array();
        if(($result=$this->select('*','mission_id=\''.$this->database->prepareStatement($mission_id).'\' OR mission_id is NULL', $sort, $order)) !== false)
            if($result->getRowCount() > 0)
                while(($data=$result->getRow()) !== false)
                    $users[$data['id']] = new User($data);

        return $users;
    }
}

?>
