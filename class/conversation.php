<?php

class Conversation
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getId() : int
    {
        return $this->data['conversation_id'];
    }

    public function getName() : string 
    {
        return $this->data['name'];
    }

    public function getNumParticipants() : int
    {
        return count($this->data['conversation_participants']);
    }

    public function getParticipants(int $excludeUserId = -1) : array
    {
        $convos = array();
        $ids = explode(',', $this->data['conversation_participants']);
        $alias = explode(',', $this->data['conversation_alias']);
        $usernames = explode(',', $this->data['conversation_usernames']);

        for($i = 0; $i < count($ids); $i++)
        {
            if(intval($ids[$i]) != $excludeUserId)
            {
                if(strlen($alias[$i]) == 0)
                {
                    $convos[intval($ids[$i])] = $usernames[$i];
                }
                else
                {
                    $convos[intval($ids[$i])] = $alias[$i];
                }
            }
        }

        return $convos;
    }


    public function getTimestampe()
    {
        return $this->data['send_timestamp'];
    }

    public function isVisible(): bool
    {
        return $this->data['is_visible'] == 0;
    }

    public function getType(): string
    {
        return $this->data['type'];
    }
}

?>