<?php

error_reporting(E_ALL);
header('Pragma: no-cache');
date_default_timezone_set("UTC");
require_once('config.inc.php');
header('Access-Control-Allow-Origin: '.$server['http'].$server['site_url']);

function echoErrorHandler($errno, $errstr, $errfile, $errline)
{    
    global $server;
    Logger::error('Main::compile()', 
        array('errno'=>$errno, 'errstr'=>$errstr, 'errfile'=>$errfile, 'errline'=>$errline));
    header('Location: '.$server['http'].$server['site_url'].'/error');
}
set_error_handler("echoErrorHandler");

try
{
    // Force HTTPS. 
    if ((strstr($server['http'], 'https') !== false) && 
        (!isset($_SERVER['HTTPS']) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off"))
    {
        header('HTTP/1.1 301 Moved Permanently');
        if(isset($_SERVER['HTTP_HOST']))
        {
            header('Location: '.$server['http'].$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); 
        }
        else
        {
            header('Location: '.$server['http'].$server['site_url']);
        }
        exit;
    }
    $main = Main::getInstance()->compile();
}
catch (Exception $e) 
{
    Logger::error("Main::compile()", array($e));
    header('Location: '.$server['http'].$server['site_url'].'/error');
}

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
        Logger::init();
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
            if($this->user->is_admin)
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
        $validModules = $this->getValidModulesForUser($this->user);

        // Select current module. 
        $moduleName = 'home';
        if(isset($_POST['action']) && in_array($_POST['action'], $validModules))
        {
            $moduleName = $_POST['action'];
        }
        else if(isset($_GET['action']) && in_array($_GET['action'], $validModules))
        {
            $moduleName = $_GET['action'];
        }
        
        // Add heartbeat to all modules if user not logged in. 
        if($this->user != null)
        {
            $defaults[] = 'heartbeat.js';
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
                $subaction = $_GET['subaction'] ?? '';
                if($subaction != 'stream')
                {
                    $this->setSiteCookie(array('sessionId'=>$sessionId, 'username'=>$username));
                }
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

        $missionConfig = MissionConfig::getInstance();

        // Note: The cookie expires in 10 min. If the user is logged in an active, 
        // the heartbeat messages should keep that going longer. 
        self::$cookie['expiration'] = time() + $missionConfig->login_timeout * 60;
        foreach ($data as $key => $val)
        {
            self::$cookie[$key] = $val;
        }

        $cookieStr = http_build_query(self::$cookie);

        setcookie(
            $config['cookie_name'], 
            $cookieStr, 
            self::$cookie['expiration'],
            '/');
            /*, 
            $server['site_url'],
            ($server['http'] == 'https://'),
            true
        );*/
    }

    public static function deleteCookie()
    {
        global $config;
        setcookie($config['cookie_name'], null, -1, '/');
    }

    public static function getCookieValue(string $key) 
    {
        return self::$cookie[$key] ?? null;
    }

    private function readCookie()
    {
        global $config;

        if(isset($_COOKIE[$config['cookie_name']]))
        {
            self::$cookie = array();
            parse_str($_COOKIE[$config['cookie_name']], self::$cookie);
        }
    }

    public static function loadTemplate($template, $replace=null, $dir='modules/')
    {
        global $config;
        global $server;

        $template = file_get_contents($config['templates_dir'].'/'.$dir.$template);

        if($replace != null)
        {
            $template = preg_replace(array_keys($replace),array_values($replace),$template);
        }

        $replace = array(
            '/%http%/'             => $server['http'],
            '/%site_url%/'         => $server['site_url'],
            '/%templates_dir%/'    => $config['templates_dir'],         
        );
        $template = preg_replace(array_keys($replace),array_values($replace),$template);

        return $template;
    }
}

?>
