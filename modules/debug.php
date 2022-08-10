<?php

class DebugModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array('run' => 'debugStream');
        $this->subStreamRequests = array();
    }

    public function debugStream() 
    {
        $missionConfig = MissionConfig::getInstance();
        if(!$missionConfig->debug)
        {
            exit();
        }

        // Get a listing of all the conversation the current user belongs to.
        $conversationsDao = ConversationsDao::getInstance();
        $conversations = $conversationsDao->getConversations($this->user->user_id);

        $DELAY_BETWEEN_MESSAGS = 5;
        $messagesDao = MessagesDao::getInstance();
        $usersDao = UsersDao::getInstance();
        
        for($i = 0; $i < 40; $i++)
        {
            $currTime = new DelayTime();

            if(rand(0, 10)< 8)
            {
                $conversationId = 1;
            }
            else
            {
                $conversationId = array_rand($conversations);
            }
            $participants = $conversations[$conversationId]->getParticipants($this->user->user_id);
            $participantId = array_rand($participants);

            $otherUser = $usersDao->getById($participantId);


            $msgData = array(
                'user_id' => $participantId,
                'conversation_id' => $conversationId,
                'text' => 'Auto generated debug message #'.$i,
                'type' => Message::TEXT,
                'sent_time' => $currTime->getTime(),
                'recv_time_hab' => $currTime->getTime(!$otherUser->is_crew),
                'recv_time_mcc' => $currTime->getTime($otherUser->is_crew),
            );

            if(($messageId = $messagesDao->sendMessage($msgData)) !== false)
            {
                echo 'New auto-generated message ID='.$messageId.'<br/>';
            }
            else
            {
                echo 'Error. Done.<br/>';
                Logger::error('Failed to send auto-generated message', $msgData);
                return;
            }
            flush();
            sleep($DELAY_BETWEEN_MESSAGS);
        }

        exit();
   }
}

?>