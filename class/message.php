<?php

class Message
{
    const TEXT = 'text';
    const FILE = 'file';
    const AUDIO = 'audio';
    const VIDEO = 'video';

    const MSG_STATUS_DELIVERED = 'Delivered';
    const MSG_STATUS_TRANSIT = 'Transit';

    private $data;
    private $file;

    public function __construct(array $data)
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

    public function __get($name)
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $result = $this->data[$name];
        }
        else if(strstr($name, '_time_') !== false)
        {
            
        }

        return $result;
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
        return ($this->getReceivedTime(!$this->data['is_crew']) <= $time->getTime()) ? self::MSG_STATUS_DELIVERED : self::MSG_STATUS_TRANSIT;
    }

    public function compileArray(User &$userPerspective, bool $remoteDest) : array
    {
        $msgData = array(
            'message_id'       => $this->data['message_id'],
            'user_id'          => $this->data['user_id'],
            'is_crew'          => $this->data['is_crew'],
            'author'           => $this->data['alias'],
            'message'          => $this->data['text'],
            'type'             => self::TEXT,
            'sent_time'        => DelayTime::convertTsForJs($this->data['sent_time']),
            'recv_time_mcc'    => DelayTime::convertTsForJs($this->data['recv_time_mcc']),
            'recv_time_hab'    => DelayTime::convertTsForJs($this->data['recv_time_hab']),
            'delivered_status' => $this->getMsgStatus(),
            'sent_from'        => $this->data['is_crew'],
        );

        $msgData['remoteDest'] = $remoteDest;

        if(boolval($this->data['is_crew']) == $remoteDest)
        {
            $msgData['recv_time'] = $msgData['recv_time_mcc'];
        }
        else
        {
            $msgData['recv_time'] = $msgData['recv_time_hab'];
        }

        if($this->data['type'] != self::TEXT && $this->file != null && $this->file->exists())
        {
            $msgData['filename'] = $this->file->original_name;
            $msgData['filesize'] = $this->file->getSize();
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