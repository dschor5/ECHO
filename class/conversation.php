<?php

/**
 * Conversation objects represent one conversation within the chat application.
 * Encapsulates 'conversations' row from database. 
 * 
 * Table Structure: 'conversations'
 * - conversation_id         (int)      Unique ID for the conversation
 * - name                    (string)   Name given to this conversation
 * - parent_conversation_id  (int)      If using nested threads, this points to 
 *                                      the parent conversation
 * - date_created            (datetime) Date when the conversation was created
 * - last_message            (datetime) when the last message was sent
 * 
 * Additional Fields:
 * - participants_id         (string)   CSV of participant ids for this convo
 * - participants_username   (string)   CSV of participant usernames for this convo
 * - participants_alias      (string)   CSV of participant aliases for this convo
 * - participants_is_crew    (bool)     CSV of participants is_crew field for this convo
 * - num_participants        (int)      Number of participants in this convo
 * - participants_both_sites (bool)     True if convo has users in both MCC and HAB
 * - thread_ids              (array)    Ids of child conversations
 * 
 * Note: The nth entry in the participant_* fields all correspond to the same account.
 * 
 * @link https://github.com/dschor5/ECHO
 */
class Conversation
{
    /**
     * Data from 'conversations' database table. 
     * @access private
     * @var array
     */
    private $data;

    /**
     * Conversation constructor. 
     * 
     * Appends object data with the field num_participants (int) and flag denoting 
     * whether the conversation has participants at both sites (MCC & HAB).
     * 
     * @param array $data Row from 'msg_files' database table. 
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        // Count number of participants linked to this conversation.
        $this->data['num_participants'] = 1;
        if(isset($data['num_participants']))
        {
            $this->data['num_participants'] = count($data['num_participants']);
        }

        // The field 'participants_both_sites' counts the number of unique 
        // users.is_crew entries for this conversation. Given that is_crew is
        // a boolean, there can only be two possible values:
        // - participants_both_sites=1 - Convo made up of only MCC or only HAB users.
        // - participants_both_sites=2 - Convo made up of both MCC and HAB users.
        // If not set, it is safer to assume both sites to enforce the comms delay. 
        if(isset($data['participants_both_sites']))
        {
            $this->data['participants_both_sites'] = (2 == $this->data['participants_both_sites']);
        }
        else
        {
            $this->data['participants_both_sites'] = true;
        }

        // Add ids for child convos if threading is enabled
        $this->data['thread_ids'] = array();
    }

    /**
     * Accessor for Conversation fields. Returns value stored in the field $name 
     * or null if the field does not exist. 
     * 
     * @param string $name Name of field being requested. 
     * @return mixed Value contained by the field requested. 
     */
    public function __get(string $name)
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $result = $this->data[$name];
        }
        else
        {
            Logger::warning('Conversation __get("'.$name.'")', $this->data);
        }

        return $result;
    }

    public function addThreadId(int $threadId)
    {
        if(!in_array($threadId, $this->thread_ids))
        {
            $this->data['thread_ids'][] = $threadId;
        }
    }

    /**
     * Get the an associative array of user_id => alias/username for all 
     * the participants in this conversation. Where, the alias is used unless
     * it is empty in which case the list defaults to the username. 
     * Optional, exclude a given user id from the results. 
     * 
     * @param int $excludeUserId Used id to exclude from the list. Default none=-1.
     * @return array Associative array with user_id => alias/usernames. 
     */
    public function getParticipants(int $excludeUserId = -1) : array
    {
        // Associative array to store user=>name pairs. 
        $participants = array();

        // Split comma separated entries from the database.
        $ids = explode(',', $this->participants_id);
        $alias = explode(',', $this->participants_alias);
        $usernames = explode(',', $this->participants_username);
        $isCrew = explode(',', $this->participants_is_crew);

        // Iterate through each entry. 
        for($i = 0; $i < count($ids); $i++)
        {
            // Check if it needs to exclude this user. 
            if(intval($ids[$i]) != $excludeUserId)
            {
                // Assign the value depending on whether there is an alias. 
                $participants[intval($ids[$i])] = 
                    array('username' => $usernames[$i],
                          'alias'    => $alias[$i],
                          'is_crew'  => $isCrew[$i]
                    );
            }
        }

        return $participants;
    }

    public function archiveConvo(ZipArchive &$zip, string $tz, bool $sepThreads, string $parentName, bool $isCrew) : bool
    {
        global $config;
        $success = true;
        $messagesDao = MessagesDao::getInstance();
        $missionConfig = MissionConfig::getInstance();
        
        $mccStr = $missionConfig->mcc_planet;
        $habStr = $missionConfig->hab_planet;

        $offset = 0;
        $numMsgs = 20;
        
        $convoStr = '';
        $convoParticipants = $this->getParticipants();
        $participantsStr = '';
        foreach($convoParticipants as $participant)
        {
            $participantsStr .= Main::loadTemplate('admin-data-save-user.txt', 
                array('/%username%/' => $participant['username'],
                        '/%home%/'   => ($participant['is_crew'] ? $habStr : $mccStr)
                ));
        }
        
        $msgStr = '';
        $offset = 0;

        $folderName = sprintf('%05d', $this->conversation_id).'-conversation';
        $ids = array($this->conversation_id);
        if($sepThreads)
        {
            if($this->parent_conversation_id != null)
            {
                $folderName = sprintf('%05d', $this->parent_conversation_id).'-'.
                    sprintf('%05d', $this->conversation_id).'-thread';
            }
        }
        else
        {
            $ids = array_merge($ids, $this->thread_ids);
        }
        $zip->addEmptyDir($folderName);


        $messages = $messagesDao->getMessagesForConvo($ids, $isCrew, $offset, $numMsgs);
        if(count($messages) == 0)
        {
            $msgStr = '<tr><td colspan="7">No messages</td></tr>';
        }
        while(count($messages) > 0 && $success)
        {
            foreach($messages as $msg)
            {
                $msgResponse = $msg->archiveMessage($zip, $folderName, $convoParticipants, $tz);
                if($msgResponse === false)
                {
                    $success = false;
                    break;
                }
                else
                {
                    $msgStr .= $msgResponse;
                }
            }
            $offset += $numMsgs;
            $messages = $messagesDao->getMessagesForConvo($ids, $isCrew, $offset, $numMsgs);
        }

        if($success)
        {
            $id     = $this->conversation_id;
            $name   = $this->name;
            $thread = '';

            if($sepThreads && $this->parent_conversation_id != null)
            {
                $id     = $this->parent_conversation_id;
                $name   = $parentName;
                $thread = Main::loadTemplate('admin-data-save-thread.txt', 
                    array('/%id%/' => $this->conversation_id,
                          '/%name%/' => htmlspecialchars($this->name)));
            }

            $time = new DelayTime('now', $tz);
            $convoStr .= Main::loadTemplate('admin-data-save-convo.txt', 
                array('/%name%/'         => htmlspecialchars($name),
                      '/%id%/'           => $id,
                      '/%thread%/'       => $thread,
                      '/%participants%/' => $participantsStr,
                      '/%messages%/'     => $msgStr,
                      '/%archive-tz%/'   => $tz,
                      '/%title%/'        => 'ECHO Archive for '.htmlspecialchars($name),
                      '/%version%/'      => $config['echo_version'],
                      '/%save-date%/'    => $time->getTime(),
                ));

            $fileName = $folderName.'.html';
            $success = $zip->addFromString($fileName, $convoStr);
        }

        return $success;
    }
    
}

?>