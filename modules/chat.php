<?php

class ChatModule extends DefaultModule
{
    public function __construct(&$main, &$user)
    {
        parent::__construct($main, $user);
        $this->subJsonRequests = array('message', 'upload', 'refresh');
        $this->subHtmlRequests = array('group');
    }

    public function compileJson(string $subaction): array
    {
        $response = array();

        if($subaction == 'refresh')
        {
            $timeKeeper = TimeKeeper::getInstance();
            $response['time_mcc'] = $timeKeeper->getMccTimeStr();
            $response['time_hab'] = $timeKeeper->getHabTimeStr();
            $response['msg_new'] = array();
            $response['msg_status'] = array();
        }

        return $response;
    }

    public function compileHtml(string $subaction) : string
    {
        global $mission;

        $timeKeeper = TimeKeeper::getInstance();

        $this->addCss('chat');
        $this->addJavascript('jquery-3.6.0.min');
        $this->addJavascript('chat');

        $this->chatRoom = $_GET['conversation'] ?? null;

        $conversationsDao = ConversationsDao::getInstance();
        $convo = $conversationsDao->getConversationById($id);


        // Load default conversaiton given subaction.
        
        // Get list of users to put in the navigation bar

        return Main::loadTemplate('modules/chat.txt', 
            array('/%username%/'=>$this->user->getUsername(),
                  '/%delay_src%/' => $this->user->isCrew() ? $mission['hab_name'] : $mission['mcc_name'],
                  '/%time_mcc%/' => $timeKeeper->getMccTimeStr(),
                  '/%time_hab%/' => $timeKeeper->getHabTimeStr(),
                  '/%chat_rooms%/' => $this->getConversationList(),
                ));
    }

    private function getConversationList(): string 
    {
        $usersDao = UsersDao::getInstance();
        $users = $usersDao->getUsers();
        
        $content = Main::loadTemplate('modules/chat-rooms.txt', array(
            '/%room_id%/'=>'', 
            '/%room_name%/'=> 'Public'
        ));

        foreach($users as $user)
        {
            if($user->getUsername() != $this->user->getUsername())
            {
                $content .= Main::loadTemplate('modules/chat-rooms.txt', array(
                    '/%room_id%/'=>'0', 
                    '/%room_name%/'=> $user->getUsername()
                ));
            }
        }

        return $content;
    }
}

?>