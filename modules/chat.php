<?php

use function PHPSTORM_META\map;

/**
 * Main chat window that processes new messages/files and displays 
 * any messages received.
 */
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
            'send'      => 'textMessage', 
            'upload'    => 'uploadFile',
            'prevMsgs'  => 'getPrevMessages',
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

    /**
     * Response for asynchronous javascript POST request 
     * to create a new thread. 
     * 
     * The function can be initiated from either another thread
     * or a parent conversation, however, threads are always added
     * to the parent conversation. 
     * 
     * The thread names must be unique and have 0 < name < 100 chars. 
     * 
     * @return array Associative array with response. 
     */
    protected function createNewThread() : array
    {
        $conversationsDao = ConversationsDao::getInstance();

        // Get the name of the thread
        $threadName = $_POST['thread_name'] ?? '';
        $response = array(
            'success' => true,
        );

        // Find the parent conversation to which the thread will be added.
        $currConvo = &$this->currConversation;
        if($this->currConversation->parent_conversation_id != null)
        {
            $currConvo = &$this->conversations[$this->currConversation->parent_conversation_id];
        }

        // Validate the thread name length
        if(strlen($threadName) > 0 && strlen($threadName) < 100)
        {
            // Iterate through all other threads in this conversation 
            // to ensure the name is unique.
            foreach($currConvo->thread_ids as $threadId)
            {
                if($this->conversations[$threadId]->name == $threadName)
                {
                    $response['success'] = false;
                    $response['error'] = 'Thread name already taken.';
                }
            }

            // If all the checks passed, then insert the new thread 
            // into the database and add the participants to it.
            if($response['success'])
            {
                $threadId = $conversationsDao->newThread($currConvo, $threadName);

                // Generate AJAX responses.
                if($threadId === false)
                {
                    $response['success'] = false;
                    $response['error'] = 'Failed to create new thread.';
                }
                else
                {
                    $response['success'] = true;
                    $response['thread_id'] = $threadId;
                    $response['error'] = '';
                }
            }
        }
        else
        {
            $response['success'] = false;
            $response['error'] = 'Invalid thread name ['.htmlspecialchars($threadName).'].';
        }

        return $response;
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
        $mission = MissionConfig::getInstance();
        $convoIds = array();
        $convoIds[] = $this->currConversation->conversation_id;
        if(!$mission->feat_convo_threads)
        {
            $convoIds = array_merge($convoIds, $this->currConversation->thread_ids);
        }
        $messages = $messagesDao->getOldMessages(
            $convoIds, $this->user->user_id, 
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

        // The VIDEO and AUDIO types are fixed for Google Chrome. 
        // Future versions of ECHO can expand this to work with more browsers. 
        if($fileType == Message::VIDEO)
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
        else // Catch-all for regular attachments
        {
            $fileType = Message::FILE;
            $fileName  = trim($_FILES['data']['name'] ?? '');
            $fileExt   = substr($fileName, strrpos($fileName, '.') + 1);
            if(finfo_open(FILEINFO_MIME_TYPE) !== false)
            {
                try {
                    Logger::info('user= '.$this->user->username.'  '.
                                'finfo_open(FILEINFO_MIME_TYPE) = '.finfo_open(FILEINFO_MIME_TYPE). 
                                '  $_FILES[data][tmp_name] = '.$_FILES['data']['tmp_name'], $_FILES);
                }
                catch(Exception $e) {}
                $fileMime  = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['data']['tmp_name']);
            }
            else
            {
                $fileMime = array_search(
                    pathinfo($_FILES['data']['tmp_name'], PATHINFO_EXTENSION),
                    $config['uploads_allowed'], 
                    true);
                if($fileMime === false) 
                {
                    $fileMime = 'unknown';
                }
            }            
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
        else if(strlen($fileName) < 3 && strlen($fileName) >= 240)
        {
            $result['error'] = 'Invalid filename (3 < length =< 240).';
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
                'from_crew'       => $this->user->is_crew,
                'conversation_id' => $this->currConversation->conversation_id,
                'text'            => '',
                'type'            => $fileType,
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
            if(($messageId = $messagesDao->sendMessage($this->user, $msgData, $fileData)) !== false)
            {
                $result = array(
                    'success' => true, 
                    'message_id' => $messageId,
                );
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

        // Get message type and body.
        $msgText = $_POST['msgBody'] ?? '';
        $msgImportant = filter_var($_POST['msgType'] ?? false, FILTER_VALIDATE_BOOLEAN) ?
            Message::IMPORTANT : Message::TEXT;

        $result = array(
            'success' => false, 
            'message_id' => -1
        );

        // Message has to have at least 1 character (e.g., "k")
        if(strlen($msgText) > 0)
        {
            // Fields to enter into the database.
            $msgData = array(
                'user_id'         => $this->user->user_id,
                'from_crew'       => $this->user->is_crew,
                'conversation_id' => $this->currConversation->conversation_id,
                'text'            => $msgText,
                'type'            => $msgImportant,
                'sent_time'       => $currTime->getTime(),
                'recv_time_hab'   => $currTime->getTime(!$this->user->is_crew),
                'recv_time_mcc'   => $currTime->getTime($this->user->is_crew),
            );
            
            // Send message.
            if(($messageId = $messagesDao->sendMessage($this->user, $msgData)) !== false)
            {
                $result = array(
                    'success' => true, 
                    'message_id' => $messageId,
                );
            }
            else
            {
                $result['error'] = 'Database error.';
                Logger::error('Failed to send message', $msgData);
            }

        }
        
        return $result;
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

        $this->sendRoomMenus();

        // Iteration counter. Used to send keep-alive messages 
        // every few seconds. 
        $iter = 1;

        // Check if the server sent a last-event-id header indicating it is reconnecting
        $headers = getallheaders();
        if(isset($headers['Last-Event-Id']) && $headers['Last-Event-Id'] > 0)
        {
            $this->sendMissedMessages($headers['Last-Event-Id']);
        }
        else
        {
            // Sleep 0.5sec to avoid interfering with initial msg load.
            usleep(self::STREAM_INIT_DELAY_SEC * self::SEC_TO_MSEC);
        }

        // Infinite loop processing data. 
        while(true)
        {
            // Send events with updates. 
            $this->sendDelayEvents();
            $this->sendNewMsgEvents();
            $this->sendNewThreads();
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

    private function sendRoomMenus() 
    {
        // Initialize conversations menu
        foreach($this->conversations as $convoId => $convo)
        {
            if($convo->parent_conversation_id == null)
            {
                $selected = $this->currConversation->conversation_id == $convoId;

                $this->sendRoom(
                    $convoId, 
                    $convo->getName($this->user->user_id),
                    $selected || $this->currConversation->parent_conversation_id == $convoId,
                    $selected,
                );
            }
        }

        $mission = MissionConfig::getInstance();
        if($mission->feat_convo_threads)
        {
            // Send threads for active conversation
            foreach($this->conversations as $convoId => $convo)
            {
                if($convo->parent_conversation_id != null)
                {
                    $this->sendThread(
                        $convo->parent_conversation_id,
                        $convo->conversation_id,
                        htmlspecialchars($convo->name),
                        $this->currConversation->conversation_id == $convoId,
                    );
                }
            }
        }
    }

    private function sendRoom(int $convoId, string $name, bool $current, bool $selected)
    {
        $this->sendEventStream(
            'room', 
            array(
                'convo_id' => $convoId,
                'convo_name' => htmlspecialchars($name),
                'convo_current' => $current,
                'convo_selected' => $selected
            )
        );
    }

    private function sendThread(int $convoId, int $threadId, string $name, bool $selected)
    {
        $this->sendEventStream(
            'thread', 
            array(
                'convo_id'    => $convoId,
                'thread_id'   => $threadId,
                'thread_name' => htmlspecialchars($name),
                'thread_selected' => $selected,
            )
        );
    }

    /**
     * Sends event stream message 'delay' anytime the current 
     * communicaiton delay changes. 
     */
    private function sendNewThreads()
    {
        $mission = MissionConfig::getInstance();

        // Only execute if threads are enabled.
        if($mission->feat_convo_threads)
        {
            $conversationsDao = ConversationsDao::getInstance();

            // Gets new threads. The query excludes all known conversation ids
            // to only get the new threads.
            $newConvos = $conversationsDao->getNewThreads(
                array_keys($this->conversations), $this->user->user_id);

            // For each new thread
            foreach($newConvos as $convoId => $convo)
            {
                // Update our cached knowledge base
                $this->conversations[$convoId] = $convo;
                $this->conversations[$convo->parent_conversation_id]->addThreadId($convoId);
                
                // If it does not have a parent, then send it even if it does not belong to 
                // the active conversation. 
                if($convo->parent_conversation_id != null)
                {
                    $this->sendEventStream(
                        'thread', 
                        array(
                            'convo_id'    => $convo->parent_conversation_id,
                            'thread_id'   => $convo->conversation_id,
                            'thread_name' => htmlspecialchars($convo->name),
                        )
                    );
                }
            }
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
    private function sendMissedMessages(int $lastId)
    {
        // Get new messages
        $time = new DelayTime();
        $timeStr = $time->getTime();
        $messagesDao = MessagesDao::getInstance();
        $mission = MissionConfig::getInstance();
        $convoIds = array();
        $convoIds[] = $this->currConversation->conversation_id;
        if(!$mission->feat_convo_threads)
        {
            $convoIds = array_merge($convoIds, $this->currConversation->thread_ids);
        }

        $offset = 0;
        $messages = $messagesDao->getMissedMessages(
            $convoIds, $this->user->user_id, $this->user->is_crew, $timeStr, $lastId, $offset);

        while(count($messages) > 0)
        {
            // Iterate through the new messages and send a unique event 
            // for each one where the msg data is JSON encoded. 
            // Use the id field to identify unique events 
            foreach($messages as $msgId => $msg)
            {
                if($msg->user_id != $this->user->user_id)
                {
                    $this->sendEventStream(
                        'msg', 
                        $msg->compileArray($this->user, $this->currConversation->participants_both_sites),
                        $msgId, 
                    );
                }
            }

            $offset += count($messages);

            $messages = $messagesDao->getMissedMessages(
                $convoIds, $this->user->user_id, $this->user->is_crew, $timeStr, $lastId, $offset);
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
        $mission = MissionConfig::getInstance();
        $convoIds = array();
        $convoIds[] = $this->currConversation->conversation_id;
        if(!$mission->feat_convo_threads)
        {
            $convoIds = array_merge($convoIds, $this->currConversation->thread_ids);
        }
        $messages = $messagesDao->getNewMessages(
            $convoIds, $this->user->user_id, $this->user->is_crew, $timeStr);

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

        // Poll database for new messages for each conversation. 
        $time = new DelayTime();
        $timeStr = $time->getTime();
        $messagesDao = MessagesDao::getInstance();
        $currNotifications = $messagesDao->getMsgNotifications(
            $this->currConversation->conversation_id, $this->user->user_id, $this->user->is_crew, $timeStr);

        if(count($currNotifications) > 0)
        {
            // Create array containing the current parent & thread ids. 
            $thisConvoAndThreads = array();
            if($this->currConversation->parent_conversation_id == null)
            {
                $thisConvoAndThreads = array_merge(
                    array($this->currConversation->conversation_id),
                    $this->currConversation->thread_ids);
            }
            else
            {
                $thisConvoAndThreads = array_merge(
                    array($this->currConversation->parent_conversation_id),
                    $this->conversations[$this->currConversation->parent_conversation_id]->thread_ids);
            }

            // Consolidate thread notifications with parent if either:
            //      a) threads are disabled OR
            //      b) the user is viewing a different convo altogether
            $mission = MissionConfig::getInstance();
            $tempNotifications = array();
            foreach($currNotifications as $convoId => $convo)
            {
                // If no threads AND message received in a thread (would only happen if disabling threads during a mission)
                // OR threads are enabled and you receive a message in a different coversation, then 
                // consolidate notifications for parent. 
                if((!$mission->feat_convo_threads && $this->conversations[$convoId]->parent_conversation_id != null) ||
                   ($mission->feat_convo_threads && !in_array($convoId, $thisConvoAndThreads)))
                {
                    // Consolidate notifications for parent conversation
                    $id = $convoId;
                    if($this->conversations[$convoId]->parent_conversation_id != null)
                    {
                        $id = $this->conversations[$convoId]->parent_conversation_id;
                    }

                    // Initialize the notification for that convo
                    if(!isset($tempNotifications[$id]))
                    {
                        $tempNotifications[$id]['num_new'] = 0;
                        $tempNotifications[$id]['num_important'] = 0;
                    }

                    // Assign results from query for number of new messages and important messages
                    $tempNotifications[$id]['num_new'] += $convo['num_new'];
                    $tempNotifications[$id]['num_important'] += $convo['num_important'];
                }
                // Otherwise, assume the notifications can be sent with the thread/conversation id. 
                else
                {
                    $tempNotifications[$convoId] = $convo;
                }
            }

            // Save notifications. 
            $currNotifications = $tempNotifications;

            // Ensure we only send new notifications by iterating through the list of new
            // notificaitons and comparing that to the previous list sent. 
            // Note that num_important is sent as a binary flag (either there were important 
            // messages or not).
            $newNotifications = array();
            foreach($currNotifications as $convoId => $msgs)
            {
                if(count($prevNotifications) == 0)
                {
                    $newNotifications[$convoId] = $currNotifications[$convoId];
                    $newNotifications[$convoId]['notif_important'] = ($msgs['num_important'] > 0) ? 1:0;
                }
                else if(!isset($prevNotifications[$convoId]))
                {
                    $newNotifications[$convoId] = $currNotifications[$convoId];
                    $newNotifications[$convoId]['notif_important'] = ($currNotifications[$convoId]['num_important']) ? 1:0;
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

        // Only add theads if enabled. 
        if($mission->feat_convo_threads)
        {
            $this->addTemplates('threads.js');
        }

        // Add flags & templates for all features enabled. 
        // The flags can be used by javascripts to enable/disable features.
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

        // Determine who can add new threads if the feature is enabled.
        if($mission->feat_convo_threads && ($this->user->is_admin || $mission->feat_convo_threads_all))
        {
            $featuresEnabled .= Main::loadTemplate('chat-feat-convo-threads-all.txt');
        }   

        // Load template. 
        return Main::loadTemplate('chat.txt', 
            array('/%username%/'           => htmlspecialchars($this->user->username),
                  '/%delay_src%/'          => $this->user->is_crew ? $mission->hab_name : $mission->mcc_name,
                  '/%chat_rooms%/'         => '',//$this->getConversationList(),
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
            if($convo->parent_conversation_id == null)
            {
                // Add flag if this is the current conversation room.
                $roomSelected = '';
                if($this->currConversation->conversation_id == $convo->conversation_id)
                {
                    $roomSelected = 'room-selected';
                }

                // If threads are enabled, then only add the list for the 
                // current parent conversation.
                $listThreads = '';
                $mission = MissionConfig::getInstance();
                if($mission->feat_convo_threads && 
                  ($this->currConversation->parent_conversation_id == $convo->conversation_id ||
                   $this->currConversation->conversation_id == $convo->conversation_id))
                {
                    // Add threads.
                    $threads = '';
                    foreach($convo->thread_ids as $threadId)
                    {
                        $threads .= Main::loadTemplate('chat-room-thread-link.txt', array(
                            '/%thread_id%/'       => $threadId,
                            '/%thread_name%/'     => htmlspecialchars($this->conversations[$threadId]->name),
                            '/%thread_selected%/' => ($this->currConversation->conversation_id == $threadId) ? 'class="thread-selected"' : '',
                        ));
                    }

                    // Link for new threads always visible for admins, but others depend on a configuration flag.
                    $newThreadLink = ($this->user->is_admin || $mission->feat_convo_threads_all) ?
                        Main::loadTemplate('chat-room-new-thread.txt') : '';

                    $listThreads = Main::loadTemplate('chat-room-thread.txt', array(
                        '/%convo-id%/'    => $convo->conversation_id,
                        '/%thread-rooms%/'=> $threads,
                        '/%new-thread%/'  => $newThreadLink,
                    ));
                }

                // Apply the template
                $content .= Main::loadTemplate('chat-rooms.txt', array(
                    '/%room_id%/'   => $convo->conversation_id,
                    '/%room_name%/' => htmlspecialchars($convo->getName($this->user->user_id)),
                    '/%selected%/'  => $roomSelected,
                    '/%threads%/'   => $listThreads,
                ));
            }
        }

        return $content;
    }
}

?>