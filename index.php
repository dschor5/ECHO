<?php

//don't cache this page
header('Pragma: no-cache');

//include config file
include_once('config.inc.php');

require_once('database/usersDao.php');
//start it up...
try
{
    Main::getInstance();
}
catch (Exception $e)
{
    //echo $e->getMessage();
}

class Main
{
    private $user = null;            //user object
    private $cookie = null;            //associative array of cookie values
    private static $instance = null;

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new Main();
        }

        return self::$instance;
    }

    private function __construct()
    {
        global $config;
        global $mission;

        // Read cookie and check if the user is logged in.
        $this->readCookie();
        $this->checkLogin();

        if($this->user != null) 
        {
            if($this->user->isAdmin())
            {
                $valid_modules = array_merge($config['modules_public'], $config['modules_user'], $config['modules_admin']);
            }
            else
            {
                $valid_modules = array_merge($config['modules_public'], $config['modules_user']);
            }
        }
        else
        {
            $valid_modules = $config['modules_public'];
        }

        $module_name = 'home';
        if(isset($_GET['action']) && in_array($_GET['action'], $valid_modules))
        {
            $module_name = $_GET['action'];
        }
        
        require_once($config['modules_dir'].'/'.$module_name.'.php');
        $module_class_name = $module_name.'Module';
        $module = new $module_class_name($this, $this->user);

        // Configure mission time
        $timeKeeper = TimeKeeper::getInstance();
        $timeKeeper->config($mission['time_epoch'], $mission['time_sec_per_day'], $mission['time_day']);
        
        // Configure communicaiton delay
        $commDelay = Delay::getInstance();

        $replace = array(
            '/%title%/' => $module->getPageTitle(),
            '/%content%/' => $module->compile(),
            '/%css_file%/' =>$module->getCss(),
            '/%js_file%/' =>$module->getJavascript(),
            '/%debug%/' =>'',
            '/%home_planet%/' => $mission['home_planet'],
            '/%away_planet%/' => $mission['away_planet'],
            '/%delay_distance%/' => $commDelay->getDistanceStr(),
            '/%delay_time%/' => $commDelay->getDelayStr(),
            '/%mission_name%/' => $mission['name'],
            '/%year%/' => date('Y'),
            '/%random%/' => rand(1, 100000),
        );

        //print information about database queries
        if((isset($this->cookie['debug']) && $this->cookie['debug'] == '1') ||
           (isset($_GET['debug']) && $_GET['debug'] == '1'))
        {
            $this->cookie['debug'] = '1';
            $replace['/%debug%/'] = $this->getDebugInfo();
        }

        if(isset($_GET['debug']))
        {
            $this->setCookie(array('debug'=>$_GET['debug']));
        }
        elseif(isset($this->cookie['debug']))
        {
            $this->setCookie(array('debug'=>$this->cookie['debug']));
        }
        else
        {
            $this->setCookie(array('debug'=>'0'));
        }

        header('Content-type: text/html; charset=utf-8');
        echo $this->loadTemplate('main.txt', $replace);
    }

    public function checkLogin()
    {
        global $config;

        // Read username and session id from the cookie.
        if(isset($this->cookie['username']) && isset($this->cookie['sessionId']))
        {
            $username = $this->cookie['username'];
            $sessionId = $this->cookie['sessionId'];

            $usersDao = UsersDao::getInstance();
            $this->user = $usersDao->getByUsername($username);

            if($this->user != null && $this->user->isValidSession($sessionId))
            {
                $this->setCookie(array('sessionId'=>$sessionId, 'username'=>$username));
            }
            else
            {
                $this->user = null;

            }
        }
    }

    public function getDebugInfo()
    {
        $db = Database::getInstance();

        $content = '<div id="debug">DEBUG INFORMATION<br/>';

        if($this->user != null)
        {
            $content .= 'USER: '.$this->user->getUsername().'<br/>';
        }

        foreach($_GET as $key=>$get)
            $content .= '<b>'.$key.'</b>=>'.$get.'<br/>';
        $content .= '<br/>';

        $err=$db->getErr();
        if ($err != false)
        {
            $content .= 'Executed '.$db->query_count .' database queries in '. $this->db->query_time .' us';
            $content .= '<br/>Error: '.$err['query'] . '<br/>' . $err['error'].'<br/>';

            foreach ($err['trace'] as $trace)
                $content .= $trace['function'] .' in '.$trace['file'] . ' on line ' . $trace['line'] .' <br/>';
        }

        //add validators
        $content .= '<a href="http://validator.w3.org/check?uri=referer">XHTML</a>&nbsp;<a href="http://jigsaw.w3.org/css-validator/validator?uri=http://musical.darioschor.com//templates/css/common.css">CSS</a></div>';

        return $content;
    }

    public function setCookie($data)
    {
        global $config;

        // Store copy of cookie variables.
        foreach ($data as $key=>$val)
            $this->cookie[$key] = $val;

        // Create cookie string.
        $cookieStr=array();
        foreach ($this->cookie as $key=>$value)
            $cookieStr[] = $key.'='.$value;
        $cookieStr = implode('&',$cookieStr);

        // Set cookie.
        setcookie($config['cookie_name'], $cookieStr, time() + $config['cookie_expire'], '/');
    }

    public function readCookie()
    {
        global $config;
        $data = null;        //data read from cookie

        if(isset($_COOKIE[$config['cookie_name']]))
        {
            $tmp = explode('&', urldecode($_COOKIE[$config['cookie_name']]));

            $this->cookie = array();
            foreach($tmp as $val)
            {
                if (strpos($val,'='))
                {
                    list($key,$val) = @explode('=',$val);
                    $this->cookie[$key] = $val;
                }
            }
        }
    }

    public static function loadTemplate($template, $replace=null)
    {
        global $config;

        $template = file_get_contents($config['templates_dir'].'/'.$template);

        if($replace != null)
        {
            $template = preg_replace(array_keys($replace),array_values($replace),$template);
        }

        $replace = array(
            '/%http%/' => $config['http'],
            '/%site_url%/' => $config['site_url'],
            '/%templates_dir%/' => $config['templates_dir'],
        );
        $template = preg_replace(array_keys($replace),array_values($replace),$template);

        return $template;
    }
}

?>
