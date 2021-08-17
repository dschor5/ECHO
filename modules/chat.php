<?php

class ChatModule extends DefaultModule
{
    private $participants;
    private $convoHasParticipantsInBothSites;
    private $conversationId;
    private $convoAccessGranted;

    public function __construct(&$user)
    {
        parent::__construct($user);
        
        $this->subJsonRequests = array('send', 'upload');
        $this->subHtmlRequests = array('group');
        $this->subStreamRequests = array('refresh');

        if(isset($_POST['conversation_id']) && intval($_POST['conversation_id']) > 0)
        {
            $this->conversationId = intval($_POST['conversation_id']);
        }
        elseif(isset($_GET['conversation_id']) && intval($_GET['conversation_id']) > 0)
        {
            $this->conversationId = intval($_GET['conversation_id']);
        }
        elseif(Main::getCookieValue('conversation_id') != null)
        {
            $this->conversationId = intval(Main::getCookieValue('conversation_id'));
        }
        else
        {
            $this->conversationId = 1;
        }

        Main::setSiteCookie(array('conversation_id'=>$this->conversationId));

        $participantsDao = ParticipantsDao::getInstance();
        $this->participants = $participantsDao->getParticipantIds($this->conversationId);
        $this->convoAccessGranted = isset($this->participants[$this->user->getId()]);
        $this->convoHasParticipantsInBothSites = (count(array_unique($this->participants)) == 2);
    }

    public function compileJson(string $subaction): array
    {
        $response = array('success' => false);

        if($this->convoAccessGranted)
        {
            $response['conversation_id'] = $this->conversationId;

            if($subaction == 'send')
            {
                $response = $this->textMessage();
            }
            elseif($subaction == 'upload')
            {
                $response = $this->uploadFile();
            }
        }
        else
        {
            $response['error'] = 'User cannot access conversation_id='.$this->conversationId;
        }

        return $response;
    }

    

    private function uploadFile()
    {
        var_dump($_FILES);
        var_dump($_POST);

        return array(
            'success' => true,
            'message_id' => 0
        );
    }

    private function textMessage()
    {
        $messagesDao = MessagesDao::getInstance();
        $currTime = new DelayTime();

        $msgText = $_POST['msgBody'] ?? '';

        $response = array(
            'success' => false, 
            'message_id' => -1
        );

        if(strlen($msgText) > 0)
        {
            $msgData = array(
                'user_id' => $this->user->getId(),
                'conversation_id' => $this->conversationId,
                'text' => $msgText,
                'filename' => null,
                'type' => Message::TEXT,
                'sent_time' => $currTime->getTime(),
                'recv_time_hab' => $currTime->getTime(true, !$this->user->isCrew(), false),
                'recv_time_mcc' => $currTime->getTime(true, $this->user->isCrew(), false),
            );
            
            if(($messageId = $messagesDao->sendMessage($msgData)) !== false)
            {
                $response = array(
                    'success' => true,
                    'message_id' => $messageId
                );
            }

        }
        
        return $response;
    }

    public function compileStream() 
    {
        $messagesDao = MessagesDao::getInstance();

        // Block invalid access. 
        if(!$this->convoAccessGranted)
        {
            echo "event: logout".PHP_EOL;
            echo "data: session expired".PHP_EOL.PHP_EOL;
            return;
        }

        $lastMsg = time();

        // Infinite loop processing data. 
        while(true)
        {
            $time = new DelayTime();
            $timeStr = $time->getTime();
            
            $messages = $messagesDao->getNewMessages($this->conversationId, $this->user->getId(), $this->user->isCrew(), $timeStr);
            if(count($messages) > 0)
            {
                foreach($messages as $msgId => $msg)
                {
                    echo "event: msg".PHP_EOL;
                    echo 'data: '.$msg->compileJson($this->user, $this->convoHasParticipantsInBothSites).PHP_EOL.PHP_EOL;
                }
                $lastMsg = time();
            }

            // Send keep-alive message every 5 seconds of inactivity. 
            if($lastMsg + 5 <= time())
            {
                echo ":\n";
                $lastMsg = time();
            }

            // Flush output to the user. 
            ob_end_flush();
            flush();

            // Check if the connection was aborted by the user (e.g., closed browser)
            if(connection_aborted())
            {
                break;
            }
            
            // Check if the cookie expired to force logout. 
            if(time() > intval(Main::getCookieValue('expiration')))
            {
                echo "event: logout".PHP_EOL;
                echo "data: session expired".PHP_EOL.PHP_EOL;
            }
            sleep(1);
        }
    }

    public function compileHtml(string $subaction) : string
    {
        global $config;
        global $mission;

        $time = new DelayTime();

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
        $this->addJavascript('media');
        $this->addJavascript('time');
        
        if($this->user->isAdmin())
        {
            $this->addHeaderMenu('User Settings', 'users');
            $this->addHeaderMenu('Mission Settings', 'mission');
        }

        
        
        $messagesDao = MessagesDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();

        $messages = $messagesDao->getMessagesReceived($this->conversationId, $this->user->getId(), $this->user->isCrew(), $time->getTime());
        $participantsDao->updateLastRead($this->conversationId, $this->user->getId(), $time->getTime());
        $messagesDao->getNewMessages($this->conversationId, $this->user->getId(), $this->user->isCrew(), $time->getTime());

        $messagesStr = '';
        foreach($messages as $message)
        {
            $messagesStr .= $message->compileHtml($this->user, $this->convoHasParticipantsInBothSites);
        }

        return Main::loadTemplate('modules/chat.txt', 
            array('/%username%/'=>$this->user->getUsername(),
                  '/%delay_src%/' => $this->user->isCrew() ? $mission['hab_name'] : $mission['mcc_name'],
                  '/%time_mcc%/' => $time->getTime(),
                  '/%time_hab%/' => $time->getTime(false),
                  '/%chat_rooms%/' => $this->getConversationList(),
                  '/%convo_id%/' => $this->conversationId,
                  '/%message-nav%/' => '',
                  '/%messages%/' => $messagesStr,
                  '/%template-msg-sent-usr%/' => Message::compileEmptyMsgTemplate('chat-msg-sent-usr.txt'),
                  '/%template-msg-sent-hab%/' => Message::compileEmptyMsgTemplate('chat-msg-sent-hab.txt'),
                  '/%template-msg-sent-mcc%/' => Message::compileEmptyMsgTemplate('chat-msg-sent-mcc.txt'),
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
            if(count($participants) > 1 || $convo->getId() == 1)
            {
                $name = $convo->getName();
            }
            else
            {
                $name = 'Private: '.array_pop($participants);
            }
            
            $content .= Main::loadTemplate('modules/chat-rooms.txt', array(
                '/%room_id%/'   => $convo->getId(),
                '/%room_name%/' => $name,
                '/%selected%/'  => ($convo->getId() == $this->conversationId) ? 'room-selected' : '',
            ));
        }

        return $content;
    }
}

?>