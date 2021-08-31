<?php

class ChatModule extends DefaultModule
{
    private $conversation = null;

    public function __construct(&$user)
    {
        parent::__construct($user);
        
        $this->subJsonRequests = array('send', 'upload', 'prevMsgs');
        $this->subHtmlRequests = array('group');
        $this->subStreamRequests = array('refresh');
        $conversationId = 1;

        if(isset($_POST['conversation_id']) && intval($_POST['conversation_id']) > 0)
        {
            $conversationId = intval($_POST['conversation_id']);
        }
        elseif(isset($_GET['conversation_id']) && intval($_GET['conversation_id']) > 0)
        {
            $conversationId = intval($_GET['conversation_id']);
        }
        elseif(Main::getCookieValue('conversation_id') != null)
        {
            $conversationId = intval(Main::getCookieValue('conversation_id'));
        }
        else
        {
            $conversationId = 1;
        }
        
        $conversationsDao = ConversationsDao::getInstance();
        // Caches all conversations for this user. This is used later to create the navication.
        $conversations = $conversationsDao->getConversationsByUserId($this->user->user_id);

        if(isset($conversations[$conversationId]))
        {
            $this->conversation = $conversations[$conversationId];
        }
        // Needed in case the cookie value was deleted by the admin.
        else
        {
            $this->conversation = $conversations[1];
        }

        Main::setSiteCookie(array('conversation_id'=>$conversationId));
    }

    public function compileJson(string $subaction): array
    {
        $response = array('success' => false);

        if($this->conversation != null)
        {
            $response['conversation_id'] = $this->conversation->getId();

            if($subaction == 'send')
            {
                $response = $this->textMessage();
            }
            elseif($subaction == 'upload')
            {
                $response = $this->uploadFile();
            }
            elseif($subaction == 'prevMsgs')
            {
                $response = $this->getPrevMessages();
            }
        }
        else
        {
            $response['error'] = 'User cannot access conversation_id='.$this->conversation->getId();
        }

        return $response;
    }

    private function getPrevMessages()
    {
        $msgId = PHP_INT_MAX;
        $numMsgs = 25;
        if(isset($_POST['message_id']) && intval($_POST['message_id']) > 0 && intval($_POST['message_id']) < PHP_INT_MAX) 
        {
            $msgId = intval($_POST['message_id']);
            $numMsgs = 10;
        }
        $time = new DelayTime();
        $response = array();

        if(intval($msgId) > 0)
        {
            $messagesDao = MessagesDao::getInstance();
            $messages = $messagesDao->getMessagesReceived(
                $this->conversation->getId(), $this->user->user_id, 
                $this->user->is_crew, $time->getTime(), $msgId, $numMsgs);
            
            $response['success'] = true;
            $response['messages'] = array();

            foreach($messages as $msg)
            {
                $response['messages'][] = $msg->compileArray($this->user, $this->conversation->hasParticipantsOnBothSites());
            }
        }
        
        $response['req'] = (count($messages) < $numMsgs) && ($msgId == PHP_INT_MAX);

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
            $fileName = $this->user->username.'_'.date('YmdHis').'.'.$fileExt;
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
                'user_id' => $this->user->user_id,
                'conversation_id' => $this->conversation->getId(),
                'text' => '',
                'type' => Message::FILE,
                'sent_time' => $currTime->getTime(),
                'recv_time_hab' => $currTime->getTime(!$this->user->is_crew),
                'recv_time_mcc' => $currTime->getTime($this->user->is_crew),
            );

            $fileData = array(
                'message_id' => 0,
                'server_name' => $serverName,
                'original_name' => $fileName,
                'mime_type' => $fileMime,
            );

            if(($messageId = $messagesDao->sendMessage($msgData, $fileData)) !== false)
            {
                $result = array(
                    'success' => true,
                    'message_id' => $messageId
                );
            }
            else
            {
                $result['error'] = 'Database error.';
            }
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
                'user_id' => $this->user->user_id,
                'conversation_id' => $this->conversation->getId(),
                'text' => $msgText,
                'type' => Message::TEXT,
                'sent_time' => $currTime->getTime(),
                'recv_time_hab' => $currTime->getTime(!$this->user->is_crew),
                'recv_time_mcc' => $currTime->getTime($this->user->is_crew),
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
        $delayObj = Delay::getInstance();
        $messagesDao = MessagesDao::getInstance();
        $conversationsDao = ConversationsDao::getInstance();
        $conversations = $conversationsDao->getConversationsByUserId($this->user->user_id);
        $conversationIds = array();
        foreach($conversations as $convoId => $convo)
        {
            if($conversationIds != $convoId)
            {
                $conversationIds[] = $convoId;
            }
        }

        // Block invalid access. 
        if($this->conversation == null)
        {
            echo "event: logout".PHP_EOL;
            echo "data: session expired".PHP_EOL.PHP_EOL;
            return;
        }

        // Sleep 0.5sec to avoid interfering with initial msg load.
        usleep(500000);

        $lastMsg = time();
        $prevNotifications = array();
        $prevDelay = -1;

        // Infinite loop processing data. 
        while(true)
        {
            $time = new DelayTime();
            $timeStr = $time->getTime();

            $delay = $delayObj->getDelay();
            if($delay != $prevDelay)
            {
                echo "event: delay".PHP_EOL;
                echo "data: ".json_encode(array('delay'=>$delayObj->getDelayStr(), 'distance'=>$delayObj->getDistanceStr())).PHP_EOL.PHP_EOL;
                $lastMsg = time();
                $prevDelay = $delay;
            }

            $messages = $messagesDao->getNewMessages($this->conversation->getId(), $this->user->user_id, $this->user->is_crew, $timeStr);
            if(count($messages) > 0)
            {
                foreach($messages as $msgId => $msg)
                {
                    echo "event: msg".PHP_EOL;
                    echo 'data: '.json_encode($msg->compileArray($this->user, $this->conversation->hasParticipantsOnBothSites())).PHP_EOL.PHP_EOL;
                }
                $lastMsg = time();
            }

            
            $notifications = $messagesDao->getMsgNotifications($conversationIds, $this->user->user_id, $this->user->is_crew, $timeStr);
            if(count($notifications) > 0)
            {
                $newNotifications = array_diff_assoc($notifications, $prevNotifications);
                foreach($newNotifications as $convoId=>$numMsgs)
                {
                    echo "event: notification".PHP_EOL;
                    echo 'data: '.json_encode(array(
                        'conversation_id' => $convoId, 
                        'num_messages'    => $numMsgs
                    )).PHP_EOL.PHP_EOL;
                    $lastMsg = time();
                }
                $prevNotifications = $notifications;
            }
            

            // Send keep-alive message every 5 seconds of inactivity. 
            if($lastMsg + 5 <= time())
            {
                echo ":".PHP_EOL.PHP_EOL;
                $lastMsg = time();
            }

            // Flush output to the user. 
            //ob_flush();
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
        $mission = MissionConfig::getInstance();

        $time = new DelayTime();

        $this->addTemplates('chat.css', 'chat.js', 'media.js', 'time.js');

        return Main::loadTemplate('chat.txt', 
            array('/%username%/'=>$this->user->username,
                  '/%delay_src%/' => $this->user->is_crew ? $mission->hab_name : $mission->mcc_name,
                  '/%chat_rooms%/' => $this->getConversationList(),
                  '/%convo_id%/' => $this->conversation->getId()
                ));
    }

    private function getConversationList(): string 
    {
        $conversationsDao = ConversationsDao::getInstance();
        $conversations = $conversationsDao->getConversationsByUserId($this->user->user_id);
    
        $content = '';
        foreach($conversations as $convo)
        {
            $participants = $convo->getParticipants($this->user->user_id);
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
                '/%selected%/'  => ($convo->getId() == $this->conversation->getId()) ? 'room-selected' : '',
            ));
        }

        return $content;
    }
}

?>