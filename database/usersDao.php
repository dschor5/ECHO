<?php

require_once("database/dao.php");
require_once("class/user.php");
require_once("database/result.php");

class UsersDao extends Dao
{
    private static $instance = null;
    private static $cache = array();
    private static $cacheFull = false;

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
        parent::__construct('users', 'user_id');
    }

    public function getById(int $id)
    {
        $user = null;

        if(isset(self::$cache[$id]))
        {
            $user = self::$cache[$id];
        }

        if($user == null)
        {
            if (($result = $this->select('*','user_id=\''.$this->database->prepareStatement($id).'\'')) !== false)
            {
                if ($result->num_rows > 0)
                {
                    $userData = $result->fetch_assoc();
                    self::$cache[$userData['user_id']] = new User($userData);
                    $user = self::$cache[$userData['user_id']];
                }
            }
        }

        return $user;
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
            if (($result = $this->select('*','username=\''.$this->database->prepareStatement($username).'\'')) !== false)
            {
                if ($result->num_rows > 0)
                {
                    $userData = $result->fetch_assoc();
                    self::$cache[$userData['user_id']] = new User($userData);
                    $user = self::$cache[$userData['user_id']];
                }
            }
        }

        return $user;
    }

    public function getUsers(string $sort='user_id', string $order='ASC'): array
    {
        if(self::$cacheFull == false)
        {
            if(($result = $this->select('*','*',$sort,$order)) !== false)
            {   
                if($result->num_rows > 0)
                {
                    // Override cache
                    self::$cache = array();
                    while(($data=$result->fetch_assoc()) != null)
                    {
                        self::$cache[$data['user_id']] = new User($data);
                    }
                    self::$cacheFull = true;
                }
            }
        }

        return self::$cache;
    }
}

?>
