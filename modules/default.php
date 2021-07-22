<?php

require_once('index.php');

abstract class DefaultModule
{
    protected $db;        // reference to database
    protected $main;        // reference to main object
    protected $user;

    protected $subJsonRequests;
    protected $subHtmlRequests;

    private $cssFiles;
    private $jsFiles;
    private $navLinks;

    public function __construct(&$main, &$user)
    {
        $this->main = &$main;
        $this->user = &$user;
        $this->db = Database::getInstance();
        $this->cssFiles = array();
        $this->jsFiles = array();
        $this->navLinks = array();
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array();
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
                array('/%filename%/'=>$file))."\n";
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
                array('/%filename%/'=>$file))."\n";
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
            $links .= '<a href="%http%%site_url%/'.$url.'">'.$name.'</a>'."\n";
        }

        $userLocation = '';
        $username = '';
        if($this->user != null)
        {
            $userLocation = $this->user->getLocation();
            $username = $this->user->getUsername();
        }

        return Main::loadTemplate('modules/header.txt', array(
            '/%links%/' => $links,
            '/%user_location%/' => $userLocation,
            '/%username%/' => $username,
        ));
    }

    public function compile()
    {
        $subaction = $_GET['subaction'] ?? '';

        if(in_array($subaction, $this->subJsonRequests))
        {
            header('Content-Type: application/json');
            echo json_encode($this->compileJson($subaction));
            exit();
        }
        
        return $this->compileHtml($subaction);
    }

    public abstract function compileJson(string $subaction): array;
    public abstract function compileHtml(string $subaction): string;
}

?>