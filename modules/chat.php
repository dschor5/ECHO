<?php

class ChatModule extends DefaultModule
{
    private $conversations;
    private $conversationId;

    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('send', 'upload');
        $this->subHtmlRequests = array('group');
        $this->subStreamRequests = array('refresh');
    }

    public function compileJson(string $subaction): array
    {
        $response = array('success' => false);
        $this->conversationId = $_COOKIE['convo-id'] ?? 1;
        $response['convo-id'] = $this->conversationId;

        if($subaction == 'send')
        {
            $msgText = $_POST['msgBody'] ?? '';
            if(strlen($msgText) > 0)
            {
                $response = $this->sendMessage($msgText);
            }
        }
        elseif($subaction == 'upload')
        {

        }

        return $response;
    }

    private function sendMessage(string $msgText)
    {
        $messageDao = MessagesDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $messageStatusDao = MessageStatusDao::getInstance();
        
        $messageDao->startTransaction();

        $msgData = array(
            'user_id' => $this->user->getId(),
            'conversation_id' => $this->conversationId,
            'text' => $msgText,
            'type' => Message::TEXT,
            'sent_time' => $this->user->getUserTimeStr(),
        );
        $messageId = $messageDao->insert($msgData);

        $partcipants = $participantsDao->getParticipantIds($this->conversationId, $this->user->getId());
        foreach($partcipants as $userId => $isCrew)
        {
            $msgStatusData = array(
                'message_id' => $messageId,
                'user_id' => $userId,
                'recv_time' => ($isCrew == $this->user->isCrew()) ? $this->user->getUserTimeStr() : null,
                'is_delivered' => ($isCrew == $this->user->isCrew()),
                'is_read' => false,
            );
            $messageStatusDao->insert($msgStatusData);
        }

        $messageDao->endTransaction();

        // Format JSON response to the user that sent the message
        $jsonResponse = array(
            'success' => true,
            'msg_id' => $messageId,
            'user_id' => $msgData['user_id'],
            'username' => $this->user->getUsername(),
            'text' => $msgText,
            'sent_time' => $msgData['sent_time'],
        );
        return $jsonResponse;
    }

    public function compileStream() 
    {
        $timeKeeper = TimeKeeper::getInstance();
        $eventTime = array();

        while(true)
        {
            $eventTime['time_mcc'] = $timeKeeper->getMccTimeStr();
            $eventTime['time_hab'] = $timeKeeper->getHabTimeStr();
            echo "event: time\n";
            echo 'data: '.json_encode($eventTime)."\n\n";

            ob_end_flush();
            flush();

            if(connection_aborted())
            {
                break;
            }
            sleep(1);
        }
    }

    public function compileHtml(string $subaction) : string
    {
        global $config;
        global $mission;

        $this->conversationId = $_GET['id'] ?? 1;
        Main::setSiteCookie(array('convo-id'=>$this->conversationId));

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
                  '/%convo_id%/' => $this->conversationId,
                  '/%template-msg-sent-user%/' => $this->compileMsgHtml(array('user'=>'DARIO SCHOR')),
                  '/%template-msg-sent-hab%/' => file_get_contents($config['templates_dir'].'/modules/chat-msg-sent-hab.txt'),
                  '/%template-msg-sent-mcc%/' => file_get_contents($config['templates_dir'].'/modules/chat-msg-sent-mcc.txt'),
                ));
    }

    private function compileMsgHtml(array $msgData = null) : string
    {
        $templateData = array();

        if($msgData != null)
        {
            $templateData = array(
                '/%user-id%/' => $msgData['user-id'],

            );
        }
        else
        {
            $templateData = array(
                '/%user-id%/' => '',

            );
        }

        

        return Main::loadTemplate($template, $templateData);
    }

    private function getConversationList(): string 
    {
        $conversationsDao = ConversationsDao::getInstance();
        $conversations = $conversationsDao->getConversationsByUserId($this->user->getId());

        $content = '';
        foreach($conversations as $convo)
        {
            $participants = $convo->getParticipants($this->user->getId());
            if(count($participants) > 1 || $convo->getId() == 1)
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