<?php

require_once('index.php');

class ChatModule extends DefaultModule
{
    public function __construct(&$main, &$user)
    {
        parent::__construct($main, $user);
        $this->subJsonRequests = array('message', 'upload', 'refresh');
        $this->subHtmlRequests = array('group');
    }

    public function getNavigation()
    {
        return 'asd';
    }

    public function compileJson(string $subaction): array
    {
        $response = array();

        if($subaction == 'refresh')
        {
            $timeKeeper = TimeKeeper::getInstance();
            $response['time_mcc'] = $timeKeeper->getMccTimeStr();
            $response['time_hab'] = $timeKeeper->getHabTimeStr();
            $response['new_messages'] = array();
            $response['']
        }

        return $response;
    }

    public function compileHtml(string $subaction) : string
    {
        global $mission;

        $this->addCss('chat');
        $this->addJavascript('jquery-3.6.0.min');
        $this->addJavascript('chat');

        // Load default conversaiton given subaction.
        
        // Get list of users to put in the navigation bar

        return Main::loadTemplate('modules/chat.txt', 
            array('/%username%/'=>$this->user->getUsername(),
                  '/%delay_src%/' => $this->user->isCrew() ? $mission['hab_name'] : $mission['mcc_name']));
    }
}

?>