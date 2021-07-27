<?php

error_reporting(E_ALL);
header('Pragma: no-cache');

require_once('config.inc.php');
require_once('database/usersDao.php');


try
{
   $main = Main::getInstance();
   $main->compile();
}
catch (Exception $e) {}

/**
 * Main class.
 */
class Main
{
    /** 
     * Instance of current user. Defaults to null if no user is logged in. 
     */
    private $user = null;

    /**
     * Local copy of parameters read/saved from the website cookie. 
     */
    private static $cookie = array();

    /** 
     * Singleton instance for Main class.
     */
    private static $instance = null;

    /**
     * Singleton constructor for Main class. 
     * Read website cookie and validate user session. 
     */
    private function __construct()
    {
        $this->readCookie();
        $this->checkLogin();
    }

    /**
     * Returns singleton instance of Main class. 
     */
    public static function getInstance() : Main
    {
        if(self::$instance == null)
        {
            self::$instance = new Main();
        }

        return self::$instance;
    }

    /**
     * Get list of valid modules for current user.
     * 
     * @return array List of valid modules.
     */
    private function getValidModulesForUser() : array
    {
        global $config;

        $validModules = $config['modules_public'];

        if($this->user != null) 
        {
            if($this->user->isAdmin())
            {
                $validModules = $config['modules_admin'];
            }
            else
            {
                $validModules = $config['modules_user'];
            }
        }
        
        return $validModules;
    } 

    /**
     * Load and compile current module. 
     */
    public function compile() 
    {
        global $config;

        // Select current module. 
        $moduleName = 'home';
        if(isset($_GET['action']) && in_array($_GET['action'], $this->getValidModulesForUser($this->user)))
        {
            $moduleName = $_GET['action'];
        }
        
        // Load module
        require_once($config['modules_dir'].'/'.$moduleName.'.php');
        $moduleClassName = $moduleName.'Module';
        $module = new $moduleClassName($this->user);

        // Compile module.
        $module->compile();
    }

    /**
     * Read cookie and validate session for current user. If successful, 
     * set $this->user to the current User. 
     * Assumes the website cookie (username & sessionId) were already read.
     */
    public function checkLogin()
    {
        global $config;

        if(isset(self::$cookie['username']) && isset(self::$cookie['sessionId']))
        {
            $username = trim(self::$cookie['username']);
            $sessionId = trim(self::$cookie['sessionId']);

            $usersDao = UsersDao::getInstance();
            $this->user = $usersDao->getByUsername($username);

            if($this->user != null && $this->user->isValidSession($sessionId))
            {
                $this->setSiteCookie(array('sessionId'=>$sessionId, 'username'=>$username));
            }
        }
    }

    /** 
     * Set website cookie with associative array. 
     * Saves a local copy of the array so that this function can be called 
     * multiple times with new parameters if needed. 
     * 
     * @param array $data Associative array of key->value pairs to add to the cookie.
     */
    public static function setSiteCookie($data)
    {
        global $config;
        global $server;

        foreach ($data as $key => $val)
        {
            self::$cookie[$key] = $val;
        }

        $cookieStr = http_build_query(self::$cookie);

        setcookie(
            $config['cookie_name'], 
            $cookieStr, 
            time() + $config['cookie_expire'], 
            '/', 
            $server['site_url'],
            ($server['http'] == 'https://'),
            true
        );
    }

    public function readCookie()
    {
        global $config;

        if(isset($_COOKIE[$config['cookie_name']]))
        {
            self::$cookie = array();
            parse_str($_COOKIE[$config['cookie_name']], self::$cookie);
        }

        return;
    }

    public static function loadTemplate($template, $replace=null)
    {
        global $config;
        global $server;

        $template = file_get_contents($config['templates_dir'].'/'.$template);

        if($replace != null)
        {
            $template = preg_replace(array_keys($replace),array_values($replace),$template);
        }

        $replace = array(
            '/%http%/' => $server['http'],
            '/%site_url%/' => $server['site_url'],
            '/%templates_dir%/' => $config['templates_dir'],
        );
        $template = preg_replace(array_keys($replace),array_values($replace),$template);

        return $template;
    }
}

?>
