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
        return Main::loadTemplate('error.txt');
    }
}


?>