<?php

abstract class DefaultModule implements Module
{
    protected $db;        // reference to database
    protected $user;

    protected $subJsonRequests;
    protected $subHtmlRequests;
    protected $subStreamRequests;

    private $templateFiles;
    private $navLinks;

    public function __construct(&$user)
    {
        $this->user = &$user;
        $this->db = Database::getInstance();
        $this->templateFiles = array(
            ($this->user != null && $this->user->is_crew) ? 'chat-hab.css' : 'chat-mcc.css');
        $this->navLinks = array();
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array();
        $this->subStreamRequests = array();
    }

    public function getPageTitle()
    {
        return 'Analog Comm Delay';
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
        $content = '';
        foreach($this->templateFiles as $file)
        {
            $extension = substr($file, strrpos($file, '.') + 1);
            $content .= "\t".Main::loadTemplate($extension.'_file.txt', 
                array('/%filename%/'=>$file)).PHP_EOL;
        }
        return $content;
    }

    protected function addHeaderMenu(string $label, string $url)
    {
        $this->navLinks[$label] = $url;
    }

    public function getHeader(): string
    {
        // Add default logout option.
        $this->navLinks['Logout'] = 'logout';

        $links = '';
        foreach($this->navLinks as $name => $url)
        {
            $links .= '<a href="%http%%site_url%/'.$url.'">'.$name.'</a>'.PHP_EOL;
        }

        $userLocation = '';
        $username = '';
        if($this->user != null)
        {
            $userLocation = $this->user->getLocation();
            $username = $this->user->alias.' ('.$this->user->username.')';
        }

        return Main::loadTemplate('header.txt', array(
            '/%links%/' => $links,
            '/%user_location%/' => $userLocation,
            '/%username%/' => $username,
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

        if(in_array($subaction, $this->subJsonRequests))
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
        elseif(in_array($subaction, $this->subStreamRequests))
        {
            header('Content-Type: text/event-stream');
            $this->compileStream();
        }
        else
        {
            header('Content-type: text/html; charset=utf-8');

            // Configure communicaiton delay
            $commDelay = Delay::getInstance();

            $replace = array(
                '/%title%/'            => $this->getPageTitle(),
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
                '/%timezone_mcc_offset%/'  => DelayTime::getTimezoneOffset(true),
                '/%timezone_hab_offset%/'  => DelayTime::getTimezoneOffset(false),
            );

            echo Main::loadTemplate('main.txt', $replace);
        }
    }

    
    public abstract function compileHtml(string $subaction): string;
    
    public abstract function compileJson(string $subaction) : array;
    
    public function compileStream()
    {
        return;
    }
}

?>