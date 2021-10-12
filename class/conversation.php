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
     * Conversation constructor. 
     * 
     * Appends object data with the field num_participants (int) and flag denoting 
     * whether the conversation has participants at both sites (MCC & HAB).
     * @param array $data Row from 'msg_files' database table. 
     */
    public function __construct($data)
    {
        $this->data = $data;

        $this->data['num_participants'] = 1;
        if(isset($data['num_participants']))
        {
            $this->data['num_participants'] = count($data['num_participants']);
        }

        $this->data['participants_both_sites'] = true;
        if(isset($data['participants_both_sites']))
        {
            $this->data['participants_both_sites'] = (2 == $this->data['participants_both_sites']);
        }
    }

    /**
     * Accessor for Conversation fields. Returns value stored in the field $name 
     * or null if the field does not exist. 
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
                    $usernames[$i] : $alias[$i];
            }
        }

        return $participants;
    }
}

?>