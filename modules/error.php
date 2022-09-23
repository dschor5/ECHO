<?php

class ErrorModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array(
            'default'      => 'showError', 
        );

        $_GET['subaction'] = 'show';
        $_POST['subaction'] = 'show';
    }

    protected function showError() : string
    {
        $this->addTemplates('common.css', 'settings.css');
        $username = ($this->user == null) ? 'n/a' : $this->user->username;
        Logger::warning('error:compileHtml user='.$username.
            ', GET='.json_encode($_GET).
            ', POST='.json_encode($_POST));
        return Main::loadTemplate('error.txt');
    }    
}


?>