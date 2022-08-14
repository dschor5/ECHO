<?php

class ChatModule extends DefaultModule
{
    /**
     * Array of all conversations for current user. 
     * Arranged as [convoId => Conversation Object].
     * @access private
     * @var array
     */
    private $conversations;

    /**
     * Reference to current conversation object from $this->conversations list. 
     * @access private
     * @var Conversation
     */
    private $currConversation;

    /**
     * Wait time (in sec) before repeating event stream loop. 
     * @access private
     * @var float
     */
    const STREAM_WAIT_BETWEEN_ITER_SEC = 1;

    /**
     * Wait time (in sec) before entering the event stream infinity loop. 
     * @access private
     * @var int 
     */
    const STREAM_INIT_DELAY_SEC = 0.5; 

    /**
     * Conversion from sec to milliseconds. 
     * @access private
     * @var int
     */
    const SEC_TO_MSEC = 1000000;

    /**
     * Send keep alive event stream message every X iterations. 
     * Should be set with STREAM_WAIT_BETWEEN_ITER_SEC in mind. 
     * @access private
     * @var int
     */
    const STREAM_MAX_ITER_BETWEEN_KEEP_ALIVE = 5;    

    /**
     * Constructor. Loads current conversation information from DB. 
     *
     * @param User $user Current logged in user. 
     */
    public function __construct(User &$user)
    {
        parent::__construct($user);
        
        $this->subJsonRequests = array(
            'send' => 'textMessage', 
            'upload'   => 'uploadFile',
            'prevMsgs' => 'getPrevMessages',
            'newThread' => 'createNewThread'
        );
        $this->subHtmlRequests = array(
            'default' => 'showChat'
        );
        $this->subStreamRequests = array(
            'refresh' => 'compileStream'
        );

        // Initialize to invalid conversation id. 
        $conversationId = -1;

        // The core chat application relies on the current conversationId 
        // to find appropriate users, poll for messages, and send messages. 
        // In order of precedences, the conversationId is loaded from:
        // 1. POST requests responding to a form
        if(isset($_POST['conversation_id']) && intval($_POST['conversation_id']) > 0)
        {
            $conversationId = intval($_POST['conversation_id']);
        }
        // 2. GET requests when loading a page
        elseif(isset($_GET['conversation_id']) && intval($_GET['conversation_id']) > 0)
        {
            $conversationId = intval($_GET['conversation_id']);
        }
        // 3. If it not provided then check the website cookie. 
        elseif(Main::getCookieValue('conversation_id') != null)
        {
            $conversationId = intval(Main::getCookieValue('conversation_id'));
        }
        // 4. Finally, if all else fails, default to the mission convo. 
        else
        {
            $conversationId = 1;
        }
        
        // Get a listing of all the conversation the current user belongs to.
        $conversationsDao = ConversationsDao::getInstance();
        $this->conversations = $conversationsDao->getConversations($this->user->user_id);

        // Check that the current user is a participant to the conversationId selected above. 
        if(isset($this->conversations[$conversationId]))
        {
            $this->currConversation = &$this->conversations[$conversationId];
        }
        // If not, then automatically select the first valid conversation that
        // was returned from the database. 
        elseif(count($this->conversations) > 0)
        {
            $firstConvo = array_keys($this->conversations)[0];
            $this->currConversation = &$this->conversations[$firstConvo];
            $conversationId = $this->currConversation->conversation_id;
        }
        // If all else fails, the conversation is set to null such that
        // the corresponding subfunctions catch the error. 
        else
        {
            $this->currConversation = null;
        }

        // Update the site cookie with the new conversationId. 
        Main::setSiteCookie(array('conversation_id'=>$conversationId));
    }

    /**
     * Compile AJAX responses. 
     *
     * @param string $subaction
     * @return array Associative array with response. 
     */
    public function compileJson(string $subaction): array
    {
        $response = array('success' => false);

        if($this->currConversation != null)
        {
            $response['conversation_id'] = $this->currConversation->conversation_id;
            $response = array_merge($response, parent::compileJson($subaction));
        }
        else
        {
            $response['error'] = 'User cannot access conversation_id='.$this->currConversation->conversation_id;
        }

        return $response;
    }

    protected function createNewThread() : array
    {
        // Receive a name. If success, return new thread id and let the javascript load that page. 
        // This should check if it is a unique name. 
    }

    /**
     * Response for asynchronous javascript POST request 
     * for previous messages in this chat.
     * 
     * Since it is difficult to predict how many messages there will be in each 
     * conversation, we don't return all the previous messages, but rather 
     * break up the functionality into two cases:
     *  1. On page load - Defaults to load the 25 most recent messages. 
     *  2. Scroll up    - When the user scrolls up, load 10 additional messages. 
     *                    Use the oldest message id already loaded as the index.
     * 
     * @return array Associative array of messages. 
     */
    protected function getPrevMessages() : array
    {
        // Default settings used when the page loads
        $msgId   = PHP_INT_MAX;
        $numMsgs = 25;
        
        // If a message id was provided via a POST request (AJAX call), then 
        // refine the query parameters to load up to 10 older messages. 
        if(isset($_POST['message_id']) && 
           intval($_POST['message_id']) > 0 && 
           intval($_POST['message_id']) < PHP_INT_MAX) 
        {
            $msgId   = intval($_POST['message_id']);
            $numMsgs = 10;
        }

        // The query is further constrained by the current timestamp. 
        $time = new DelayTime();
        $response = array();

        // Query for old messages received. 
        $messagesDao = MessagesDao::getInstance();
        $messages = $messagesDao->getOldMessages(
            $this->currConversation->conversation_id, $this->user->user_id, 
            $this->user->is_crew, $time->getTime(), $msgId, $numMsgs);
        
        // Build response with an array of messages. 
        $response['success'] = true;
        $response['messages'] = array();

        foreach($messages as $msg)
        {
            $response['messages'][] = $msg->compileArray($this->user, 
                $this->currConversation->participants_both_sites);
        }
        
        // Flag 'reg' is true if on-page load it found fewer than 25 messages. 
        // This is used by the javascript to adjust the page layout. 
        $response['req'] = (count($messages) < $numMsgs) && ($msgId == PHP_INT_MAX);

        return $response; 
    }

    /**
     * Response for asynchronous javascript POST request 
     * to upload a file (sent as a new message).
     * 
     * The funciton validates the upload (type, name, size) before
     * moving it to the uploads directory and entering the information 
     * into the database. 
     * 
     * Implementation Notes:
     * - The original plan was to return success/failure along with 
     *   an error message, then leave the polling function get the 
     *   message to display on the screen. Unfortunately, that led to 
     *   race conditions depending on the polling & database insertion. 
     *   So on some occations, the message was not displayed to the user. 
     *   The problem was resolved by returning the status along with 
     *   the new message. 
     * - All messages get an MCC and HAB timestamp regardless of where
     *   the conversation participants are. Checks are performed when
     *   polling for new messages. 
     *
     * @return array Associative array of messages. 
     *               Required fields: 
     *                  - success = true/false 
     *               Optional fields:
     *                  - error = error message
     *                  - msg fields
     */
    protected function uploadFile() : array
    {
        global $config;
        global $server;

        // Get the file type. 
        $fileType  = trim($_POST['type'] ?? Message::FILE);

        // For regular attachments, get the filename, extension, and mime type. 
        if($fileType == Message::FILE)
        {
            $fileName  = trim($_FILES['data']['name'] ?? '');
            $fileExt   = substr($fileName, strrpos($fileName, '.') + 1);
            $fileMime  = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['data']['tmp_name']);
        }
        // Otherwise, Video and Audio messages will create a custom filename
        // based on the current timestamp and force the extension & mime type. 
        // TODO: Can we support more multimedia types?
        elseif($fileType == Message::VIDEO)
        {
            $fileExt  = 'mkv';
            $fileMime = 'video/webm';
            $dt = new DateTime('NOW');
            $fileName = $fileType.'_'.$dt->format('YmdHisv').'.'.$fileExt;
        }
        elseif($fileType == Message::AUDIO)
        {
            $fileExt  = 'mkv';
            $fileMime = 'audio/webm';
            $dt = new DateTime('NOW');
            $fileName = $fileType.'_'.$dt->format('YmdHisv').'.'.$fileExt;
        }
        
        // Get the file size regardless of the type. 
        $fileSize  = intval($_FILES['data']['size'] ?? 0);

        // All files will be renamed and stored in the uploads directory on the server. 
        // This prevents overwriting files if different versions of the same document 
        // are uploaded and also makes it harder for people to guess filenames. 
        $serverName = ServerFile::generateFilename($config['uploads_dir']);
        $fullPath = $server['host_address'].$config['uploads_dir'].'/'.$serverName;
        
        // Start building the response. 
        $result = array('success' => false);

        // Validate upload type. 
        if(!in_array(strtolower($fileType), array(Message::FILE, Message::VIDEO, Message::AUDIO)))
        {
            $result['error'] = 'Invalid upload type.';
        }
        // Validate filename. Min 1 char filename + period + extension. 
        else if(strlen($fileName) < 3)
        {
            $result['error'] = 'Invalid filename.';
        }
        // Validate file type. 
        else if(!isset($config['uploads_allowed'][$fileMime]))
        {
            $result['error'] = 'Invalid file type uploaded. (MimeType)';
        }
        // Validate extension
        else if(!in_array($fileExt, $config['uploads_allowed']))
        {
            $result['error'] = 'Invalid file type uploaded. (Extension)';
        }
        // Validate filesize. 
        else if($fileSize <= 0 || $fileSize > ServerFile::getMaxUploadSize())
        {
            $result['error'] = 'Invalid file size (0 < size < '.ServerFile::getMaxUploadSize().')';
        }
        // Move the file to the uploads directory. 
        else if(!move_uploaded_file($_FILES['data']['tmp_name'], $fullPath))
        {
            $result['error'] = 'Error writing file.';
        }
        else
        {
            // If all the previous checks passed and we successfully moved the 
            // file to the uploads directory, then the last step is to add the 
            // information to the database. 

            // Create entry for messages table. 
            $currTime = new DelayTime();
            $msgData = array(
                'user_id'         => $this->user->user_id,
                'conversation_id' => $this->currConversation->conversation_id,
                'text'            => '',
                'type'            => Message::FILE,
                'sent_time'       => $currTime->getTime(),
                'recv_time_hab'   => $currTime->getTime(!$this->user->is_crew),
                'recv_time_mcc'   => $currTime->getTime($this->user->is_crew),
            );
            
            // Create entry for the msg_files table. 
            $fileData = array(
                'message_id'    => 0,
                'server_name'   => $serverName,
                'original_name' => $fileName,
                'mime_type'     => $fileMime,
            );

            // Execute both database queries. 
            $messagesDao = MessagesDao::getInstance();
            if(($messageId = $messagesDao->sendMessage($msgData, $fileData)) !== false)
            {
                // Get the new message_id and build the Message object 
                // to compile the response for the user. 
                $fileData['message_id'] = $messageId;
                $newMsg = new Message(
                    array_merge(
                        $msgData, 
                        $fileData, 
                        array('username' => $this->user->username, 
                            'alias'    => $this->user->alias, 
                            'is_crew'  => $this->user->is_crew)
                        )
                    );
                
                // Compile message to send back to the calling javascript.
                // This part takes into account whether to use the MCC or HAB 
                // timestamp for the message even though we know the user should
                // be receiving their own message immediately. 
                $newMsgData = $newMsg->compileArray($this->user, 
                    $this->currConversation->participants_both_sites);
                $result = array_merge(array('success' => true), $newMsgData);
            }
            else
            {
                $result['error'] = 'Database error.';
                Logger::error('Failed to send message', array_merge($msgData, $fileData));
            }
        }

        return $result;
    }

    /**
     * Response for asynchronous javascript POST request 
     * to send a text message to the current conversation. 
     * 
     * Implementation Notes:
     * - The original plan was to return success/failure along with 
     *   an error message, then leave the polling function get the 
     *   message to display on the screen. Unfortunately, that led to 
     *   race conditions depending on the polling & database insertion. 
     *   So on some occations, the message was not displayed to the user. 
     *   The problem was resolved by returning the status along with 
     *   the new message. 
     * - All messages get an MCC and HAB timestamp regardless of where
     *   the conversation participants are. Checks are performed when
     *   polling for new messages. 
     *
     * @return array Associative array of messages. 
     *               Required fields: 
     *                  - success = true/false 
     *               Optional fields:
     *                  - error = error message
     *                  - msg fields
     */
    protected function textMessage() : array
    {
        $messagesDao = MessagesDao::getInstance();
        $currTime = new DelayTime();

        $msgText = $_POST['msgBody'] ?? '';
        $msgImportant = filter_var($_POST['msgType'] ?? false, FILTER_VALIDATE_BOOLEAN) ?
            Message::IMPORTANT : Message::TEXT;

        $response = array(
            'success' => false, 
            'message_id' => -1
        );

        if(strlen($msgText) > 0)
        {
            $msgData = array(
                'user_id' => $this->user->user_id,
                'conversation_id' => $this->currConversation->conversation_id,
                'text' => $msgText,
                'type' => $msgImportant,
                'sent_time' => $currTime->getTime(),
                'recv_time_hab' => $currTime->getTime(!$this->user->is_crew),
                'recv_time_mcc' => $currTime->getTime($this->user->is_crew),
            );
            
            // Send the message. If this fails, then 
            if(($messageId = $messagesDao->sendMessage($msgData)) !== false)
            {
                $newMsg = new Message(
                    array_merge(
                        $msgData, 
                        array('message_id' => $messageId,
                            'username' => $this->user->username, 
                            'alias' => $this->user->alias, 
                            'is_crew' => $this->user->is_crew)
                        )
                    );

                // Compile message to send back to the calling javascript.
                // This part takes into account whether to use the MCC or HAB 
                // timestamp for the message even though we know the user should
                // be receiving their own message immediately. 
                $newMsgData = $newMsg->compileArray($this->user, 
                    $this->currConversation->participants_both_sites);
                $response = array_merge(array('success' => true), $newMsgData);
            }
            else
            {
                $result['error'] = 'Database error.';
                Logger::error('Failed to send message', $msgData);
            }

        }
        
        return $response;
    }

    /**
     * Infinite loop where the server sends a stream of events rather than 
     * responding to periodic polls from the application for new data. 
     * 
     * Types of messages supported:
     * - delay        - Updates current communication delay and distance between HAB and MCC. 
     * - msg          - New message received. Multiple instances of this messages can be sent
     *                  per second and each contains a unique id to ensure the client knows 
     *                  there is no duplicate informaiton. 
     * - notification - Notifies the client that the current user received messages in 
     *                  another conversation. 
     * - keep-alive   - Empty message sent if no activity was recorded for more than X sec
     *                  to ensure the conneciton is kept alive. 
     * 
     * Implementation Notes:
     * - To avoid interfering with the on page load request for messages, 
     *   there is a hardcoded 0.5sec delay before starting the infinite loop. 
     * - Inifinite loop executing at 1Hz. 
     * 
     * Reference:
     * - https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events
     *
     * 
     * @return void
     */
    public function compileStream() 
    {
        // Block invalid access. 
        if($this->currConversation == null)
        {
            return;
        }

        $missionConfig = MissionConfig::getInstance();

        // Get a list of all conversations to monitor for msg notifications. 
        // Essentially, all the conversations the user belongs to except the 
        // active conversations. 
        $conversationIds   = array_keys(array_diff_key(
            $this->conversations, array($this->currConversation->conversation_id => 0)));

        // Sleep 0.5sec to avoid interfering with initial msg load.
        usleep(self::STREAM_INIT_DELAY_SEC * self::SEC_TO_MSEC);
        
        // Iteration counter. Used to send keep-alive messages 
        // every few seconds. 
        $iter = 1;

        // Infinite loop processing data. 
        while(true)
        {
            // Send events with updates. 
            $this->sendDelayEvents();
            $this->sendNewMsgEvents();
            $this->sendNotificationEvents();

            // Send keep-alive message every X seconds of inactivity. 
            if($iter % self::STREAM_MAX_ITER_BETWEEN_KEEP_ALIVE == 0)
            {
                $this->sendEventStream(null);
            }

            // Flush output to the user. 
            while (ob_get_level() > 0) 
            {
                ob_end_flush();
            }
            flush();

            // Check if the connection was aborted by the user (e.g., closed browser)
            if(connection_aborted())
            {
                break;
            }

            // Repeat in 1sec
            usleep(self::STREAM_WAIT_BETWEEN_ITER_SEC * self::SEC_TO_MSEC);
            $iter++;
        } 
    }

    /**
     * Sends event stream message 'delay' anytime the current 
     * communicaiton delay changes. 
     */
    private function sendDelayEvents()
    {
        // Keep track of previous delay as long as the object is active. 
        static $prevDelay = -1;

        // Get the current delay. 
        $delayObj = Delay::getInstance();
        $delay = $delayObj->getDelay();

        // Send event if the value differs from the past messages. 
        if($delay != $prevDelay)
        {
            $this->sendEventStream(
                'delay', 
                array(
                    'delay'    => $delayObj->getDelayStr(), 
                    'distance' => $delayObj->getDistanceStr()
                )
            );
            $prevDelay = $delay;
        }
    }

    /**
     * Sends event stream message 'msg' for each new message 
     * received for the current conversation. 
     */
    private function sendNewMsgEvents()
    {
        // Get new messages
        $time = new DelayTime();
        $timeStr = $time->getTime();
        $messagesDao = MessagesDao::getInstance();
        $messages = $messagesDao->getNewMessages(
            $this->currConversation->conversation_id, 
            $this->user->user_id, $this->user->is_crew, $timeStr);

        // Iterate through the new messages and send a unique event 
        // for each one where the msg data is JSON encoded. 
        // Use the id field to identify unique events 
        foreach($messages as $msgId => $msg)
        {
            $this->sendEventStream(
                'msg', 
                $msg->compileArray($this->user, $this->currConversation->participants_both_sites),
                $msgId, 
            );
        }
    }

    /**
     * Sends event stream message 'notification' anytime new messages
     * are received on other conversations. 
     */
    private function sendNotificationEvents()
    {
        // Keep track of previous notifications sent to avoid duplicates. 
        static $prevNotifications = array();

        // Conversation ids for which we want notifications. This list is 
        // static once declared. If new conversations or threads are created
        // then the page will reload and this variable will be updated. 
        static $conversationIds = array();
        if(count($conversationIds) == 0)
        {
            $conversationIds = array_keys(array_diff_key(
                $this->conversations, array($this->currConversation->conversation_id => 0)));
        }

        // Poll database for new messages for each conversation. 
        $time = new DelayTime();
        $timeStr = $time->getTime();
        $messagesDao = MessagesDao::getInstance();
        $currNotifications = $messagesDao->getMsgNotifications(
            array_keys($this->conversations), $this->user->user_id, $this->user->is_crew, $timeStr);

        if(count($currNotifications) > 0)
        {
            // Ensure we only send new notifications. 
            $newNotifications = array();

            foreach($currNotifications as $convoId => $msgs)
            {
                if(count($prevNotifications) == 0)
                {
                    $newNotifications[$convoId] = $currNotifications[$convoId];
                    $newNotifications[$convoId]['notif_important'] = ($msgs['num_important'] > 0) ? 1:0;
                }
                else if($prevNotifications[$convoId]['num_new'] != $currNotifications[$convoId]['num_new'])
                {
                    $newNotifications[$convoId] = $currNotifications[$convoId];
                    $newNotifications[$convoId]['notif_important'] = 
                        ($prevNotifications[$convoId]['num_important'] != $currNotifications[$convoId]['num_important']) ? 1:0;
                }
            }

            // Send a new message indicating the conversation id and num messages. 
            foreach($newNotifications as $convoId=>$numMsgs)
            {
                if($convoId != $this->currConversation->conversation_id)
                {
                    $this->sendEventStream(
                        'notification', 
                        array(
                            'conversation_id' => $convoId,
                            'num_messages'    => $numMsgs['num_new'],
                            'num_important'   => $numMsgs['num_important'],
                            'notif_important' => $numMsgs['notif_important'],
                        )
                    );
                }
            }

            // Track notifications already sent. 
            $prevNotifications = $currNotifications;
        }
    }

    /**
     * Compile HTML for Chat module. 
     *
     * @param string $subaction N/A for this module. 
     * @return string HTML output. 
     */
    public function compileHtml(string $subaction) : string
    {
        global $config;

        // The the mission settings
        $mission = MissionConfig::getInstance();

        // Add templates for this module. 
        $this->addTemplates('chat.css', 'chat.js', 'media.js', 'time.js');

        $featuresEnabled = ''.
            (($mission->feat_audio_notification)  ? Main::loadTemplate('chat-feat-audio-notification.txt')  : '').
            (($mission->feat_badge_notification)  ? Main::loadTemplate('chat-feat-badge-notification.txt')  : '').
            (($mission->feat_unread_msg_counts)   ? Main::loadTemplate('chat-feat-unread-msg-counts.txt')   : '').
            (($mission->feat_convo_list_order)    ? Main::loadTemplate('chat-feat-convo-list-order.txt')    : '').
            (($mission->feat_est_delivery_status) ? Main::loadTemplate('chat-feat-est-delivery-status.txt') : '').
            (($mission->feat_progress_bar)        ? Main::loadTemplate('chat-feat-progress-bar.txt')        : '').
            (($mission->feat_markdown_support)    ? Main::loadTemplate('chat-feat-markdown-support.txt')    : '').
            (($mission->feat_important_msgs)      ? Main::loadTemplate('chat-feat-important-msgs.txt')      : '').
            (($mission->feat_convo_threads)       ? Main::loadTemplate('chat-feat-convo-threads.txt')       : '');

        // Load template. 
        return Main::loadTemplate('chat.txt', 
            array('/%username%/'           =>$this->user->username,
                  '/%delay_src%/'          => $this->user->is_crew ? $mission->hab_name : $mission->mcc_name,
                  '/%chat_rooms%/'         => $this->getConversationList(),
                  '/%convo_id%/'           => $this->currConversation->conversation_id,
                  '/%max_upload_size%/'    => ServerFile::getHumanReadableSize(ServerFile::getMaxUploadSize()),
                  '/%allowed_file_types%/' => implode(', ', $config['uploads_allowed']),
                  '/%download-link%/'      => Main::loadTemplate('download-link.txt', 
                                              array('/%link%/' => '#', '/%filename%/' => '', '/%filesize%/' => '')),
                  '/%features_enabled%/'  => $featuresEnabled
                ));
    }

    /**
     * Compile conversation list for left navigation on chat window. 
     *
     * @return string Navigation display. 
     */
    private function getConversationList(): string 
    {
        $content = '';

        // Iterate through each conversation. 
        foreach($this->conversations as $convo)
        {
            // Get the list of participants for each conversation to 
            // figure out what name to give this chat. 
            $participants = $convo->getParticipants($this->user->user_id);
            if(count($participants) > 1 || $convo->conversation_id == 1)
            {
                $name = $convo->name;
            }
            else
            {
                $userInfo = array_pop($participants);
                $name = 'Private: '.(strlen($userInfo['alias']) != 0) ? $userInfo['alias'] : $userInfo['username'];
            }
            
            // Apply the template
            $content .= Main::loadTemplate('chat-rooms.txt', array(
                '/%room_id%/'   => $convo->conversation_id,
                '/%room_name%/' => $name,
                '/%selected%/'  => ($convo->conversation_id == $this->currConversation->conversation_id) ? 'room-selected' : '',
            ));
        }

        return $content;
    }
}

?>