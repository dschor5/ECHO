<?php

class Message
{
    const TEXT = 'text';
    const FILE = 'file';
    const AUDIO = 'audio';
    const VIDEO = 'video';

    private $data;
    private $file;

    public function __construct($data)
    {
        $this->data = $data; // requires union with corresponding msg_status
        $this->file = null;
        if($this->data['type'] != self::TEXT)
        {
            $this->file = new FileUpload(
                array_intersect_key($this->data, 
                    array_flip(array('message_id', 'server_name', 'original_name', 'mime_type')))
            );
        }
    }

    public function getId() : int
    {
        return $this->data['message_id'];
    }

    private function getReceivedTime(bool $isCrew) : string
    {
        if($isCrew)
        {
            return $this->data['recv_time_hab'];
        }
        return $this->data['recv_time_mcc'];
    }

    private function getMsgStatus() : string
    {
        $time = new DelayTime();
        return ($this->getReceivedTime(!$this->data['is_crew']) <= $time->getTime()) ? 'Delivered' : 'Transit';
    }

    private function getTime(string $name, $withTz=true) : string
    {
        $time = new DelayTime($this->data[$name]);
        return $time->getTime(true, false);
    }

    private function getTimeUTC(string $name) : string
    {
        $time = new DelayTime($this->data[$name]);
        return $time->getTimeUtc();
    }

    public function compileArray(User &$userPerspective) : array
    {
        $msgData = array(
            'message_id'       => $this->data['message_id'],
            'user_id'          => $this->data['user_id'],
            'author'           => $this->data['alias'],
            'message'          => $this->data['text'],
            'type'             => self::TEXT,
            'sent_time'        => $this->getTime('sent_time'),
            'recv_time_mcc'    => $this->getTime('recv_time_mcc'),
            'recv_time_hab'    => $this->getTime('recv_time_hab'),
            'delivered_status' => $this->getMsgStatus(),
        );

        if($this->data['type'] != self::TEXT && $this->file != null && $this->file->exists())
        {
            $msgData['filename'] = $this->file->original_name;
            $msgData['filesize'] = $this->file->getHumanReadableSize();
            $msgData['type'] = $this->file->getTemplateType();
            $msgData['mime_type'] = $this->file->mime_type;
        }

        // If authored by this user
        if($userPerspective->user_id == $this->data['user_id'])
        {
            $msgData['source'] = 'usr';
        }
        // Else authored by someone else on the habitat
        elseif($this->data['is_crew'])
        {
            $msgData['source'] = 'hab';
        }
        // Or authored by someone else in MCC. 
        else
        {
            $msgData['source'] = 'mcc';
        }

        return $msgData;
    }
}

?>