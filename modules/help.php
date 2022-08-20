<?php

class HelpModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array(
            'main'      => 'showHelp', 
        );
    }

    protected function showHelp() : string
    {
        $this->addTemplates('common.css', 'settings.css');
        return Main::loadTemplate('help.txt');
    }
}


?>