<?php

require_once('index.php');

class ChatModule extends DefaultModule
{
    public function getNavigation()
    {
        return 'asd';
    }

    public function compile()
    {
        $this->addCss('chat');

        

        return Main::loadTemplate('modules/chat.txt', array('/%username%/'=>$this->user->getUsername()));
    }
}

?>