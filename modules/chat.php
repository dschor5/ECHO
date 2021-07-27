<?php

class ChatModule extends DefaultModule
{
    private $conversations;
    private $conversationId;

    public function __construct(&$main, &$user)
    {
        parent::__construct($main, $user);
        $this->subJsonRequests = array('send_message', 'upload', 'refresh');
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

    public function compileStream() 
    {
        while(true)
        {
            
        }
    }

    public function compileHtml(string $subaction) : string
    {
        global $mission;

        $this->conversationId = $_GET['id'] ?? 1;

        $timeKeeper = TimeKeeper::getInstance();

        $this->addCss('common');
        $this->addCss('chat');
        if($this->user->isCrew())
        {
            $this->addCss('chat-hab');
        }
        else
        {
            $this->addCss('chat-mcc');
        }
        $this->addJavascript('jquery-3.6.0.min');
        $this->addJavascript('chat');

        if($this->user->isAdmin())
        {
            $this->addHeaderMenu('User Settings', 'users');
            $this->addHeaderMenu('Mission Settings', 'mission');
        }

        

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
        $conversationsDao = ConversationsDao::getInstance();
        $conversations = $conversationsDao->getConversationsByUserId($this->user->getId());

        $content = '';
        foreach($conversations as $convo)
        {
            $participants = $convo->getParticipants($this->user->getId());
            if(count($participants) > 1)
            {
                $name = $convo->getName();
            }
            else
            {
                $name = 'Private: '.array_pop($participants);
            }

            if($convo->getId() == $this->conversationId)
            {
                $content .= Main::loadTemplate('modules/chat-rooms.txt', array(
                    '/%room_id%/'   => $convo->getId(),
                    '/%room_name%/' => $name,
                    '/%selected%/'  => 'room-selected',
                ));
            }
            else
            {
                $content .= Main::loadTemplate('modules/chat-rooms.txt', array(
                    '/%room_id%/'   => $convo->getId(),
                    '/%room_name%/' => $name,
                    '/%selected%/'  => '',
                ));
            }
        }

        return $content;
    }
}

?>