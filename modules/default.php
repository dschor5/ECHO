<?php

require_once('index.php');

abstract class DefaultModule
{
    protected $db;        // reference to database
    protected $main;        // reference to main object
    protected $user;

    protected $subJsonRequests;
    protected $subHtmlRequests;

    private $css_file;
    private $js_file;

    public function __construct(&$main, &$user)
    {
        $this->main = &$main;
        $this->user = &$user;
        $this->db = Database::getInstance();
        $this->cssFiles = array();
        $this->jsFiles = array();
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array();
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
            $content .= "\t".Main::loadTemplate('css_file.txt', 
                array('/%filename%/'=>$file))."\n";
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
            $content .= "\t".Main::loadTemplate('javascript_file.txt', 
                array('/%filename%/'=>$file))."\n";
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