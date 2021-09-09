<?php

class DataModule extends DefaultModule
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
        $this->addTemplates('settings.css', 'users.js');

        return '';
    }

}

?>