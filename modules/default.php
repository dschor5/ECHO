<?php

require_once('index.php');

abstract class DefaultModule
{
    protected $db;        // reference to database
    protected $main;        // reference to main object
    protected $user;
    private $css_file;
    private $js_file;

    public function __construct(&$main, &$user)
    {
        $this->main = &$main;
        $this->user = &$user;
        $this->db = Database::getInstance();
        $this->cssFiles = array();
        $this->jsFiles = array();
    }

    public function getPageTitle()
    {
        return 'Analog Comm Delay';
    }

    public function addCss(string $newCssFile)
    {
        $this->cssFiles[] = $newCssFile;
    }

    public function getCss(): string
    {
        $content = '';
        foreach($this->cssFiles as $file)
        {
            $content .= Main::loadTemplate('css_file.txt', 
                array('/%filename%/'=>$file));
        }
        return $content;
    }

    public function addJavascript(string $newJsFile)
    {
        $this->jsFiles[] = $newJsFile;
    }

    public function getJavascript(): string
    {
        $content = '';
        foreach($this->jsFiles as $file)
        {
            $content .= Main::loadTemplate('javascript_file.txt', 
                array('/%filename%/'=>$file));
        }
        return $content;
    }

    public function requiresLogin(): bool
    {
        return False;
    }

    public function requiresAdmin(): bool
    {
        return False;
    }

    abstract public function compile();
}

?>