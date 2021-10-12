<?php

/**
 * Conversation objects represent one conversation within the chat application.
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
     * Conversation constructor. Appends object data with the field num_participants 
     * that counts the number of people belonging to this chat. 
     * @param array $data Row from 'msg_files' database table. 
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->data['num_participants'] = count($this->data['participant_ids']);
    }

    /**
     * Accessor for Conversation fields. Returns value stored in the field $name 
     * or null if the field does not exist. 
     * @param string $name Name of field being requested. 
     * @return mixed Value contained by the field requested. 
     */
    public function __get(string $name) : mixed
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $result = $this->data[$name];
        }

        return $result;
    }

    /**
     * Get the an associative array of user_id => alias/username for all 
     * the participants in this conversation. Where, the alias is used unless
     * it is empty in which case the list defaults to the username. 
     * Optional, explude a given user id from the results. 
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
                    $username[$i] : $alias[$i];
            }
        }

        return $participants;
    }

    /**
     * Returns true if the conversation has participants both at the HAB and MCC. 
     * If the corresponding field was not precomputed in the SQL query, then assume
     * the answer is TRUE as that is safer in terms of enforcing comm delays. 
     * 
     * @return bool True if participants at both MCC and HAB. 
     */
    public function hasParticipantsOnBothSites() : bool
    {
        if(isset($this->data['participants_both_sites']))
        {
            return $this->data['participants_both_sites'] == 2;
        }
        return true;
    }
}

?>