<?php

abstract class DefaultModule implements Module
{
    protected $db;        // reference to database
    protected $user;

    protected $subJsonRequests;
    protected $subHtmlRequests;
    protected $subStreamRequests;

    private $templateFiles;

    public function __construct(&$user)
    {
        $this->user = &$user;
        $this->db = Database::getInstance();
        $this->templateFiles = array();
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array();
        $this->subStreamRequests = array();
    }

    protected function addTemplates(string ...$newFile)
    {
        foreach($newFile as $f)
        {
            $this->templateFiles[] = $f;
        }
    }

    private function getTemplates(): string
    {
        // Add default templates
        $defaults = array(
            ($this->user != null && $this->user->is_crew) ? 'chat-hab.css' : 'chat-mcc.css',
            'common.css',
            'jquery-ui.css',
            'jquery-ui.structure.css', 
            'jquery-ui.theme.css', 
            'jquery-3.6.0.min.js', 
            'jquery-ui.min.js',
        );

        $this->templateFiles = array_merge($defaults, $this->templateFiles);

        $content = '';
        foreach($this->templateFiles as $file)
        {
            $extension = substr($file, strrpos($file, '.') + 1);
            $content .= "\t".Main::loadTemplate($extension.'_file.txt', 
                array('/%filename%/'=>$file)).PHP_EOL;
        }
        return $content;
    }

    public function getHeader(): string
    {
        $userLocation = '';
        $username = '';
        $alias = '';
        $links = '';
        $navLinks = array();

        if($this->user != null)
        {
            $userLocation = $this->user->getLocation();
            $alias        = $this->user->alias;
            $username     = $this->user->username;

            $navLinks[] = array(
                'url'  => 'chat',
                'name' => 'Chat',
                'icon' => 'home'
                );
            /*$navLinks[] = array(
                'url'  => 'preferences',
                'name' => 'Preferences',
                'icon' => 'pencil'
                );
            */
            if($this->user->is_admin)
            {
                $navLinks[] = array(
                    'url'  => 'admin/users', 
                    'name' => 'User Accounts', 
                    'icon' => 'person');
                $navLinks[] = array(
                    'url'  => 'admin/mission', 
                    'name' => 'Mission Settings',
                    'icon' => 'gear');
                $navLinks[] = array(
                    'url'  => 'admin/delay', 
                    'name' => 'Delay Settings',
                    'icon' => 'clock');
                $navLinks[] = array(
                    'url'  => 'admin/data',
                    'name' => 'Data Management',
                    'icon' => 'document');
            }

            $navLinks[] = array(
                'url'  => 'logout',
                'name' => 'Logout',
                'icon' => 'power'
                );

            $action = $_GET['action'] ?? '';
            $subaction = $_GET['subaction'] ?? '';
            $currUrl = $action.(strlen($subaction) > 0 ? '/'.$subaction : '');

            foreach($navLinks as $link)
            {
                if($currUrl != $link['url'])
                {
                    $links .= Main::loadTemplate('nav-link.txt', array(
                        '/%url%/'  => $link['url'],
                        '/%name%/' => $link['name'],
                        '/%icon%/' => $link['icon']
                    ));
                }
            }
        }

        return Main::loadTemplate('header.txt', array(
            '/%links%/'         => $links,
            '/%user_location%/' => $userLocation,
            '/%alias%/'         => $alias,
            '/%username%/'      => $username,
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
        
        Logger::error($subaction);

        // Only allow requests from this server. 
        header('Access-Control-Allow-Origin: '.$server['http'].$server['site_url']);

        if(array_key_exists($subaction, $this->subJsonRequests))
        {
            header('Content-Type: application/json');
            $response = $this->compileJson($subaction);
            if($config['debug'])
            {
                $db = Database::getInstance();
                $response['debug'] = $db->getErr();
            }
            echo json_encode($response);
        }
        elseif(array_key_exists($subaction, $this->subStreamRequests))
        {
            header('Content-Type: text/event-stream');
            $this->compileStream();
        }
        else
        {
            header('Content-type: text/html; charset=utf-8');

            // Configure communicaiton delay
            $commDelay = Delay::getInstance();

            $inMcc = 'true';
            if($this->user != null)
            {
                $inMcc = ($this->user->is_crew) ? 'false' : 'true';
            }

            $replace = array(
                '/%title%/'            => $mission->name.' - Comms',
                '/%content%/'          => $this->compileHtml($subaction),
                '/%templates%/'        => $this->getTemplates(),
                '/%header%/'           => $this->getHeader(),
                '/%home_planet%/'      => $mission->mcc_planet,
                '/%away_planet%/'      => $mission->hab_planet,
                '/%delay_distance%/'   => $commDelay->getDistanceStr(),
                '/%delay_time%/'       => $commDelay->getDelayStr(),
                '/%mission_name%/'     => $mission->name,
                '/%year%/'             => date('Y'),
                '/%random%/'           => rand(1, 100000),
                '/%epoch%/'            => DelayTime::getEpochUTC(),
                '/%time_sec_per_day%/' => 24*60*60, // TODO
                '/%time_day%/'         => $mission->hab_day_name,
                '/%hab_time_format%/'  => 'true', // TODO
                '/%timezone_mcc_offset%/'  => DelayTime::getTimezoneOffsetfromUTC(true),
                '/%timezone_hab_offset%/'  => DelayTime::getTimezoneOffsetfromUTC(false),
                '/%in_mcc%/'           => $inMcc,
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
           
            //$ret = $this->{$this->subJsonRequests[$subaction]}();
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
}

?>