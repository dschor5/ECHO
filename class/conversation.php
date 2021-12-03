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
 * - participant_ids         (string)   CSV of participant ids for this convo
 * - participant_usernames   (string)   CSV of participant usernames for this convo
 * - participant_aliases     (string)   CSV of participant aliases for this convo
 * - num_participants        (int)      Number of participants in this convo
 * - participants_both_sites (bool)     True if convo has users in both MCC and HAB
 * 
 * Note: The nth entry in the participant_* fields all correspond to the same account.
 * 
 * @link https://github.com/dschor5/AnalogDelaySite
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
        $this->data['participants_both_sites'] = true;
        if(isset($data['participants_both_sites']))
        {
            $this->data['participants_both_sites'] = (2 == $this->data['participants_both_sites']);
        }
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
        $ids = explode(',', $this->data['participant_ids']);
        $alias = explode(',', $this->data['participants_aliases']);
        $usernames = explode(',', $this->data['participant_usernames']);

        // Iterate through each entry. 
        for($i = 0; $i < count($ids); $i++)
        {
            // Check if it needs to exclude this user. 
            if(intval($ids[$i]) != $excludeUserId)
            {
                // Assign the value depending on whether there is an alias. 
                $participants[intval($ids[$i])] = (strlen($alias[$i]) == 0) ?
                    $usernames[$i] : $alias[$i];
            }
        }

        return $participants;
    }
}

?>