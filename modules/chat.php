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
        global $config;
        global $server;

        $messagesDao = MessagesDao::getInstance();
        $currTime = new DelayTime();

        // Inputs provided by the script. 
        $fileType  = trim($_POST['type'] ?? 'file');
        if($fileType == 'file')
        {
            $fileName  = trim($_FILES['data']['name'] ?? '');
            $fileExt   = substr($fileName, strrpos($fileName, '.') + 1);
            $fileMime  = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['data']['tmp_name']);
        }
        else
        {
            if($fileType == 'video')
            {
                $fileExt = 'mkv';
                $fileMime = 'video/webm';
            }
            else 
            {
                $fileExt = 'mkv';
                $fileMime = 'audio/webm';
            }
            $fileName = $this->user->getUsername().'_'.date('YmdHis').'.'.$fileExt;
        }
        
        $fileSize  = intval($_FILES['data']['size'] ?? 0);

        // Server name to use for the file.
        $serverName = FileUpload::generateFilename();
        $fullPath = $server['host_address'].$config['uploads_dir'].'/'.$serverName;
        
        $result = array(
            'success' => false,
        );

        if(!in_array(strtolower($fileType), array('file', 'audio', 'video')))
        {
            $result['error'] = 'Invalid upload type.';
        }
        else if(strlen($fileName) < 3)
        {
            // Min 1 char name, period, 1 char extension.
            $result['error'] = 'Invalid filename.';
        }
        else if(!isset($config['uploads_allowed'][$fileMime]))
        {
            $result['error'] = 'Invalid file type uploaded. (MimeType)';
        }
        else if(!in_array($fileExt, $config['uploads_allowed']))
        {
            $result['error'] = 'Invalid file type uploaded. (Extension)';
        }
        else if($fileSize <= 0 || $fileSize > 10485760)
        {
            $result['error'] = 'Invalid file size (0 < size < 10485760)';
        }
        else if(!move_uploaded_file($_FILES['data']['tmp_name'], $fullPath))
        {
            $result['error'] = 'Error writing file.';
        }
        else
        {
            $msgData = array(
                'user_id' => $this->user->getId(),
                'conversation_id' => $this->conversationId,
                'text' => '',
                'type' => Message::FILE,
                'sent_time' => $currTime->getTime(),
                'recv_time_hab' => $currTime->getTime(true, !$this->user->isCrew(), false),
                'recv_time_mcc' => $currTime->getTime(true, $this->user->isCrew(), false),
            );

            $fileData = array(
                'message_id' => 0,
                'server_name' => $serverName,
                'original_name' => $fileName,
                'mime_type' => $fileMime,
            );

            if(($messageId = $messagesDao->sendMessage($msgData, $fileData)) !== false)
            {
                $response = array(
                    'success' => true,
                    'message_id' => $messageId
                );
            }
            else
            {
                $result['error'] = 'Database error.';
            }
            
            $result['success'] = true;
        }

        return $result;
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

        return Main::loadTemplate('chat.txt', 
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
                  '/%template-msg-file%/' => Message::compileEmptyMsgTemplate('chat-msg-file.txt'),
                  '/%template-msg-img%/' => Message::compileEmptyMsgTemplate('chat-msg-image.txt'),
                  '/%template-msg-audio%/' => Message::compileEmptyMsgTemplate('chat-msg-audio.txt'),
                  '/%template-msg-video%/' => Message::compileEmptyMsgTemplate('chat-msg-video.txt'),
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
            
            $content .= Main::loadTemplate('chat-rooms.txt', array(
                '/%room_id%/'   => $convo->getId(),
                '/%room_name%/' => $name,
                '/%selected%/'  => ($convo->getId() == $this->conversationId) ? 'room-selected' : '',
            ));
        }

        return $content;
    }
}

?>