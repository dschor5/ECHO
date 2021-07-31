<?php

class Message
{
    const TEXT = 'text';
    const FILE = 'file';
    const AUDIO = 'audio';
    const VIDEO = 'video';

    private $data;

    public function __construct($data)
    {
        $this->data = $data; // requires union with corresponding msg_status
    }

    private function getReceivedTime(bool $mccPerspective) : string
    {
        if($this->data['is_crew'])
        {
            return $this->data['recv_time_hab'];
        }
        return $this->data['recv_time_mcc'];
    }

    private function getMsgStatus() : string
    {
        return ($this->data['is_delivered']) ? 'Delivered' : 'In Transit';
    }

    public function compile(User &$userPerspective) : string 
    {
        global $config;

        $msgData = array(
            '/%message-id%/'    => $this->data['message_id'],
            '/%user-id%/'       => $this->data['user_id'],
            '/%author%/'        => $this->data['alias'],
            '/%message%/'       => $this->data['text'],
            '/%msg-sent-time%/' => $this->data['sent_time'],
            '/%msg-recv-time%/' => $this->getReceivedTime($this->data['is_crew']),
            '/%msg-status%/'    => $this->getMsgStatus(),
        );
        
        // If authored by this user
        if($userPerspective->getId() == $this->data['user_id'])
        {
            $template = 'modules/chat-msg-sent-usr.txt';
        }
        // Else authored by someone else on the habitat
        elseif($this->data['is_crew'])
        {
            $template = 'modules/chat-msg-sent-hab.txt';
        }
        // Or authored by someone else in MCC. 
        else
        {
            $template = 'modules/chat-msg-sent-mcc.txt';
        }

        return Main::loadTemplate($template, $msgData);
    }

}

?>