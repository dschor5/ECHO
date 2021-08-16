<?php

require_once('database/database.php');

abstract class DefaultModule
{
    protected $db;        // reference to database
    protected $user;

    protected $subJsonRequests;
    protected $subHtmlRequests;
    protected $subStreamRequests;

    private $cssFiles;
    private $jsFiles;
    private $navLinks;

    public function __construct(&$user)
    {
        $this->user = &$user;
        $this->db = Database::getInstance();
        $this->cssFiles = array();
        $this->jsFiles = array();
        $this->navLinks = array();
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array();
        $this->subStreamRequests = array();
    }

    public function getPageTitle()
    {
        return 'Analog Comm Delay';
    }

    protected function addCss(string $newCssFile)
    {
        $this->cssFiles[] = $newCssFile;
    }

    public function getCss(): string
    {
        $content = '';
        foreach($this->cssFiles as $file)
        {
            $content .= "\t".Main::loadTemplate('css_file.txt', 
                array('/%filename%/'=>$file)).PHP_EOL;
        }
        return $content;
    }

    protected function addJavascript(string $newJsFile)
    {
        $this->jsFiles[] = $newJsFile;
    }

    public function getJavascript(): string
    {
        $content = '';
        foreach($this->jsFiles as $file)
        {
            $content .= "\t".Main::loadTemplate('javascript_file.txt', 
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
            $username = $this->user->getAlias().' ('.$this->user->getUsername().')';
        }

        return Main::loadTemplate('modules/header.txt', array(
            '/%links%/' => $links,
            '/%user_location%/' => $userLocation,
            '/%username%/' => $username,
        ));
    }

    public function compile()
    {
        global $mission;
        global $server;

        $subaction = '';
        if(isset($_POST['subaction']) && $_POST['subaction'] != null)
        {
            $subaction = $_POST['subaction'];
        }
        elseif(isset($_GET['subaction']) && $_GET['subaction'] != null)
        {
            $subaction = $_GET['subaction'];
        }
        
        header('Access-Control-Allow-Origin: *');//.$server['http'].$server['site_url']);

        if(in_array($subaction, $this->subJsonRequests))
        {
            header('Content-Type: application/json');
            echo json_encode($this->compileJson($subaction));
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
                '/%css_file%/'         =>$this->getCss(),
                '/%js_file%/'          =>$this->getJavascript(),
                '/%header%/'           => $this->getHeader(),
                '/%home_planet%/'      => $mission['home_planet'],
                '/%away_planet%/'      => $mission['away_planet'],
                '/%delay_distance%/'   => $commDelay->getDistanceStr(),
                '/%delay_time%/'       => $commDelay->getDelayStr(),
                '/%mission_name%/'     => $mission['name'],
                '/%year%/'             => date('Y'),
                '/%random%/'           => rand(1, 100000),
                '/%epoch%/'            => DelayTime::getEpoch(),
                '/%time_sec_per_day%/' => $mission['time_sec_per_day'],
                '/%time_day%/'         => $mission['time_day'],
                '/%timezone_offset%/'  => DelayTime::getTimezoneOffset(),                   
            );

            echo Main::loadTemplate('main.txt', $replace);
        }
    }

    public abstract function compileJson(string $subaction): array;
    public abstract function compileHtml(string $subaction): string;
    public function compileStream()
    {
        return;
    }
}

?>