<?php

class ErrorModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
    }

    public function compileJson(string $subaction): array
    {
        return array();
    }

    public function getHeader(): string
    {
        return '';
    }

    public function compileHtml(string $subaction) : string
    {
        $this->addTemplates('common.css', 'settings.css');
        $username = ($this->user == null) ? 'n/a' : $this->user->username;
        Logger::warning('error:compileHtml user='.$username.', '.json_encode($_GET));
        return Main::loadTemplate('error.txt');
    }
}


?>