<?php

/**
 * Abstract class to define an applicaiton module defining 
 * a specific group of user interactions. 
 * 
 * 
 */
abstract class DefaultModule implements Module
{
    /**
     * Referenced to logged in user. 
     * @access protected
     * @var User
     */
    protected $user;

    /**
     * Associative array linking valid asynchronous javascript requests
     * to their respective function handlers. 
     * Example: array(ajaxRequest => functionName)
     * @access protected
     * @var array
     */
    protected $subJsonRequests;

    /**
     * Associative array linking valid asynchronous javascript requests
     * to their respective function handlers. 
     * Example: array(htmlRequest => functionName)
     * @access protected
     * @var array
     */
    protected $subHtmlRequests;

    /**
     * Associative array linking valid event stream requests to their
     * respective function handlers. 
     * Example: array(streamRequest => functionName)
     * @access protected
     * @var array
     */
    protected $subStreamRequests;

    /**
     * Array of CSS and JS files to load with the current HTML page. 
     * @access protected
     * @var array
     */
    private $templateFiles;

    /**
     * Default module constructor. 
     */
    public function __construct(&$user)
    {
        $this->user = &$user;
        $this->templateFiles = array();
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array();
        $this->subStreamRequests = array();
    }

    /**
     * Add css or javascript templates to load with this module. 
     * Subsequent functions will use the extension to load the 
     * corresponding template. 
     *
     * @param string ...$newFile One or more filenames. 
     */
    protected function addTemplates(string ...$newFile)
    {
        foreach($newFile as $f)
        {
            $this->templateFiles[] = $f;
        }
    }

    /**
     * Get all the css and javascript files to load with the current page. 
     *
     * @return string HTML function calls to load css and javascript files. 
     */
    private function getTemplates(): string
    {
        // Add default templates used by all modules. 
        $defaults = array(
            ($this->user != null && $this->user->is_crew) ? 'chat-hab.css' : 'chat-mcc.css',
            'common.css',
            'jquery-ui.css',
            'jquery-ui.structure.css', 
            'jquery-ui.theme.css', 
            'jquery-3.6.0.min.js', 
            'jquery-ui.min.js',
        );

        // Merge the default and custom lists together. 
        $this->templateFiles = array_merge($defaults, $this->templateFiles);

        // Apply the corresponding template depending on the file extension. 
        $content = '';
        foreach($this->templateFiles as $file)
        {
            $extension = substr($file, strrpos($file, '.') + 1);
            $content .= "\t".Main::loadTemplate($extension.'_file.txt', 
                array('/%filename%/'=>$file)).PHP_EOL;
        }
        return $content;
    }

    /**
     * Compile HTML for application top menu that contains the name of the 
     * mission, name of logged in user, and menu of options for the user. 
     * 
     * @return string HTML for top menu bar. 
     */
    public function getHeader(): string
    {
        // Default info for current user: 
        $userPlanet = ''; 
        $userName     = ''; 
        $userAlias        = ''; 

        // Associative array for each navigation link. 
        // - Links to appear in the order in which they are added to the array.
        // - Each link contains:
        //      - url  - Relative path from $server['site_url']
        //      - name - Name to display for each link
        //      - icon - Name of jquery icon used
        $navLinks = array();

        if($this->user != null)
        {
            // Assign info for logged in user
            $userPlanet = $this->user->getLocation();
            $userAlias  = $this->user->alias;
            $userName   = $this->user->username;

            // Default links for all users. 
            $navLinks[] = array('url' => 'chat',        'name' => 'Chat',        'icon' => 'home');
                        
            // Links for admin users only
            if($this->user->is_admin)
            {
                $navLinks[] = array('url' => 'admin/users',   'name' => 'User Accounts',    'icon' => 'person');
                $navLinks[] = array('url' => 'admin/mission', 'name' => 'Mission Settings', 'icon' => 'gear');
                $navLinks[] = array('url' => 'admin/delay',   'name' => 'Delay Settings',   'icon' => 'clock');
                $navLinks[] = array('url' => 'admin/data',    'name' => 'Data Management',  'icon' => 'document');
            }

            // Add help and logout option for all users
            $navLinks[] = array('url' => 'help',        'name' => 'Help',        'icon' => 'help');
            $navLinks[] = array('url' => 'logout', 'name' => 'Logout', 'icon' => 'power');

            // Build url for current path. 
            $action    = $_GET['action'] ?? '';
            $subaction = $_GET['subaction'] ?? '';
            $currUrl   = $action.(strlen($subaction) > 0 ? '/'.$subaction : '');

            // Build every link. Skip if it mathes the current path. 
            $htmlLinks = '';
            foreach($navLinks as $link)
            {
                if($currUrl != $link['url'])
                {
                    $htmlLinks .= Main::loadTemplate('nav-link.txt', array(
                        '/%url%/'  => $link['url'],
                        '/%name%/' => $link['name'],
                        '/%icon%/' => $link['icon']
                    ));
                }
            }
        }

        return Main::loadTemplate('header.txt', array(
            '/%links%/'         => $htmlLinks,
            '/%user_location%/' => htmlspecialchars($userPlanet),
            '/%alias%/'         => htmlspecialchars($userAlias),
            '/%username%/'      => htmlspecialchars($userName),
        ));
    }

    public function compile()
    {
        global $server;
        global $config;

        $mission = MissionConfig::getInstance();

        $subaction = '';
        if(isset($_POST['subaction']) && $_POST['subaction'] != null)
        {
            $subaction = $_POST['subaction'];
        }
        elseif(isset($_GET['subaction']) && $_GET['subaction'] != null)
        {
            $subaction = $_GET['subaction'];
        }

        // Only allow requests from this server. 
        header('Access-Control-Allow-Origin: '.$server['http'].$server['site_url']);

        if(isset($_GET['ajax']))
        {
            header('Content-Type: application/json');
            if(array_key_exists($subaction, $this->subJsonRequests))
            {
                $response = $this->compileJson($subaction);   
            }
            else
            {
                $response = array('success' => false);
            }
            echo json_encode($response);
        }
        elseif(isset($_GET['stream']))
        {
            if(array_key_exists($subaction, $this->subStreamRequests))
            {
                header('Content-Type: text/event-stream');
                $this->compileStream();
            }
            else
            {
                // TODO - What HTTP header do we send to show an invalid request?
                header("HTTP/1.1 404 Not Found");
            }
        }
        else
        {
            header('Content-type: text/html; charset=utf-8');

            // Configure communicaiton delay
            $commDelay = Delay::getInstance();

            $inMcc = 'true';
            $timeoutWindow = '';
            if($this->user != null)
            {
                $inMcc = ($this->user->is_crew) ? 'false' : 'true';
                $this->addTemplates('timeout.js');
                $timeoutWindow = Main::loadTemplate('timeout-window.txt');
            }

            $replace = array(
                '/%title%/'            => htmlspecialchars($mission->name).' - Comms',
                '/%content%/'          => $this->compileHtml($subaction),
                '/%templates%/'        => $this->getTemplates(),
                '/%header%/'           => $this->getHeader(),
                '/%home_planet%/'      => htmlspecialchars($mission->mcc_planet),
                '/%away_planet%/'      => htmlspecialchars($mission->hab_planet),
                '/%delay_distance%/'   => $commDelay->getDistanceStr(),
                '/%delay_time%/'       => $commDelay->getDelayStr(),
                '/%mission_name%/'     => htmlspecialchars($mission->name),
                '/%year%/'             => date('Y'),
                '/%random%/'           => rand(1, 100000),
                '/%epoch%/'            => DelayTime::getEpochUTC(),
                '/%time_sec_per_day%/' => 24*60*60, // TODO
                '/%time_day%/'         => htmlspecialchars($mission->hab_day_name),
                '/%hab_time_format%/'  => 'true', // TODO
                '/%timezone_mcc_offset%/'  => DelayTime::getTimezoneOffsetfromUTC(true),
                '/%timezone_hab_offset%/'  => DelayTime::getTimezoneOffsetfromUTC(false),
                '/%in_mcc%/'           => $inMcc,
                '/%timeout-window%/'   => $timeoutWindow,
                '/%timeout-sec%/'      => $mission->login_timeout,
            );

            echo Main::loadTemplate('main.txt', $replace);
        }
    }
    
    public function compileHtml(string $subaction): string
    {
        $ret = '';

        if(array_key_exists($subaction, $this->subHtmlRequests))
        {
            $ret = call_user_func(array($this, $this->subHtmlRequests[$subaction]));
        }
        elseif(array_key_exists('default', $this->subHtmlRequests))
        {
            $ret = call_user_func(array($this, $this->subHtmlRequests['default']));
        }
        else
        {
            header("HTTP/1.1 404 Not Found");
        }

        return $ret;
    }
    
    public function compileJson(string $subaction) : array
    {
        $ret = array();

        if(array_key_exists($subaction, $this->subJsonRequests))
        {
            $ret = call_user_func(array($this, $this->subJsonRequests[$subaction]));
        }
        else
        {
            $ret['success'] = false;
            $ret['error'] = 'Unknown request.';
        }

        return $ret;
    }
    
    public function compileStream()
    {
        return;
    }

    /**
     * Send Event from the server. 
     *
     * @param string|null $name Name of event. If null, assume it is a keep alive message.
     * @param array|null $data  Data to send with the event. 
     * @param integer|null $id Unique id given to the event (or null if not applicable).
     * @return integer
     */
    protected function sendEventStream(?string $name, ?array $data = null, int $id = null)
    {
        if($name == null)
        {
            echo ':'.PHP_EOL.PHP_EOL;
        }
        else
        {
            echo 'event: '.$name.PHP_EOL;
            if($id != null) 
            {
                echo 'id: '.$id.PHP_EOL;
            }
            echo 'data: '.json_encode($data).PHP_EOL.PHP_EOL;
        }
    }
}

?>
