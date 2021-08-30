<?php

class MissionModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('validate', 'save');
        $this->subHtmlRequests = array('show');
    }

    public function compileJson(string $subaction): array
    {
        return array();
    }

    public function compileHtml(string $subaction) : string
    {
        $this->addTemplates('common.css', 'settings.css',
            'jquery-3.6.0.min.js', 'users.js');
    

        $this->addHeaderMenu('Chat', 'chat');
        $this->addHeaderMenu('User Settings', 'users');

        return '';
    }

}

?>