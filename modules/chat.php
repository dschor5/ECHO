<?php

class ChatModule extends DefaultModule
{
    private $conversation;
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
        elseif(isset($_COOKIE['conversation_id']) && intval($_COOKIE['conversation_id']) > 0)
        {
            $this->conversationId = intval($_COOKIE['conversation_id']);
        }
        else
        {
            $this->conversationId = 1;
        }

        Main::setSiteCookie(array('conversation_id'=>$this->conversationId));

        $participantsDao = ParticipantsDao::getInstance();
        $this->convoAccessGranted = $participantsDao->canUserAccessConvo($this->conversationId, $this->user->getId());
    }

    public function compileJson(string $subaction): array
    {
        $response = array('success' => false);

        if($this->convoAccessGranted)
        {
        
            $response['conversation_id'] = $this->conversationId;

            // TODO - Validate the user can post to this conversation 

            if($subaction == 'send')
            {
                $msgText = $_POST['msgBody'] ?? '';
                if(strlen($msgText) > 0)
                {
                    try {
                        $response = $this->sendMessage($msgText);
                    } catch (Exception $e) {
                        var_dump($e);
                    }
                    
                }
            }
            elseif($subaction == 'upload')
            {

            }
        }
        else
        {
            $response['error'] = 'User cannot access conversation_id='.$this->conversationId;
        }

        return $response;
    }

    private function sendMessage(string $msgText)
    {
        $messageDao = MessagesDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $messageStatusDao = MessageStatusDao::getInstance();
        $conversationsDao = ConversationsDao::getInstance();
        
        $messageDao->startTransaction();

        $currTime = new DelayTime();
        

        $msgData = array(
            'user_id' => $this->user->getId(),
            'conversation_id' => $this->conversationId,
            'text' => $msgText,
            'filename' => null,
            'type' => Message::TEXT,
            'sent_time' => $currTime->getTime(),
            'recv_time_hab' => $currTime->getTime(true, !$this->user->isCrew()),
            'recv_time_mcc' => $currTime->getTime(true, $this->user->isCrew()),
        );

        $messageId = $messageDao->insert($msgData);

        $msgStatusData = array(
            'message_id' => $messageId,
            'user_id' => $this->user->getId(),
            'is_read' => true,
        );
        $messageStatusDao->insert($msgStatusData);

        $partcipants = $participantsDao->getParticipantIds($this->conversationId);
        foreach($partcipants as $userId => $isCrew)
        {
            if($userId != $this->user->getId())
            {
                $msgStatusData = array(
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'is_read' => false,
                );
                $messageStatusDao->insert($msgStatusData);
            }
        }

        // Finally, update the timestamp for the last message received
        $conversationsDao->update(array('last_message'=>$currTime->getTime()), 'conversation_id='.$this->conversationId);

        $messageDao->endTransaction();

        // Format JSON response to the user that sent the message
        $jsonResponse = array(
            'success' => true,
            'msg_id' => $messageId,
            'user_id' => $msgData['user_id'],
            'username' => $this->user->getUsername(),
            'alias' => $this->user->getAlias(),
            'sent_time' => $msgData['sent_time'],
            'recv_time_hab' => $msgData['recv_time_hab'],
            'recv_time_mcc' => $msgData['recv_time_mcc'],
        );
        return $jsonResponse;
    }

    public function compileStream() 
    {
        $eventTime = array();
        
        $messagesDao = MessagesDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $lastRead = $participantsDao->getLastRead($this->conversationId, $this->user->getId());
        // TODO - Validate user has access to this conversation

        while(true)
        {
            $currTime = new DelayTime();
            $eventTime['time_mcc'] = $currTime->getTime();
            $eventTime['time_hab'] = $currTime->getTime(false);
            echo "event: time\n";
            echo 'data: '.json_encode($eventTime)."\n\n";

            $messagesDao->updateReadFlag($this->conversationId, $this->user->getId(), $this->user->isCrew(), $currTime->getTime());
            $messages = $messagesDao->getMessagesReceived($this->conversationId, $this->user->getId(), $this->user->isCrew(), $currTime->getTime(), 0, $lastRead);
            $lastRead = $currTime->getTime();
            $participantsDao->update(array('last_read'=>$lastRead), 
                'conversation_id=\''.$this->conversationId.'\' AND user_id=\''.$this->user->getId().'\'');

            foreach($messages as $msgId => $msg)
            {
                echo "event: msg\n";
                echo 'data: '.$msg->compileJson($this->user)."\n\n";
            }

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

        if($this->user->isAdmin())
        {
            $this->addHeaderMenu('User Settings', 'users');
            $this->addHeaderMenu('Mission Settings', 'mission');
        }

        $messagesDao = MessagesDao::getInstance();
        try 
        {
            $messagesDao->updateReadFlag($this->conversationId, $this->user->getId(), $this->user->isCrew(), $time->getTime());
            $messages = $messagesDao->getMessagesReceived($this->conversationId, $this->user->getId(), $this->user->isCrew(), $time->getTime());
            $participantsDao = ParticipantsDao::getInstance();
            $participantsDao->update(array('last_read'=>$time->getTime()), 
                'conversation_id=\''.$this->conversationId.'\' AND user_id=\''.$this->user->getId().'\'');
        } catch (Exception $e) {
            var_dump($e);
        }
        

        $messagesStr = '';
        foreach($messages as $message)
        {
            $messagesStr .= $message->compileHtml($this->user);
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
                  '/%template-msg-sent-usr%/' => $this->compileEmptyMsgTemplate('chat-msg-sent-usr.txt'),
                  '/%template-msg-sent-hab%/' => $this->compileEmptyMsgTemplate('chat-msg-sent-hab.txt'),
                  '/%template-msg-sent-mcc%/' => $this->compileEmptyMsgTemplate('chat-msg-sent-mcc.txt'),
                ));
    }

    private function compileEmptyMsgTemplate(string $template) : string
    {
        $templateData = array(
            '/%message-id%/'    => '',
            '/%user-id%/'       => '',
            '/%author%/'        => '',
            '/%message%/'       => '',
            '/%msg-sent-time%/' => '',
            '/%msg-recv-time%/' => '',
            '/%msg-status%/'    => '',
        );

        return Main::loadTemplate('modules/'.$template, $templateData);
    }

    private function getConversationList(): string 
    {
        try {
            $conversationsDao = ConversationsDao::getInstance();
            $conversations = $conversationsDao->getConversationsByUserId($this->user->getId());
        } catch (Exception $e) {
            var_dump($e);
        }
        

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