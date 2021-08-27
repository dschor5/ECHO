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
        $this->addCss('common');
        $this->addCss('settings');
        if($this->user->is_crew)
        {
            $this->addCss('chat-hab');
        }
        else
        {
            $this->addCss('chat-mcc');
        }

        return Main::loadTemplate('error.txt');
    }
}


?>