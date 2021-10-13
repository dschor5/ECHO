<?php

/**
 * Message objects represent one message within the chat application.
 * Encapsulates 'messages' row from database. 
 * 
 * Implementation Notes:
 * - Each message is assigned a type from: TEXT, FILE, AUDIO, or VIDEO. 
 * 
 * @link https://github.com/dschor5/AnalogDelaySite
 */
class Message
{
    /**
     * Constant message type: TEXT
     */
    const TEXT = 'text';

    /**
     * Constant message type: FILE
     */
    const FILE = 'file';

    /**
     * Constant message type: AUDIO
     */
    const AUDIO = 'audio';

    /**
     * Constant message type: VIDEO
     */
    const VIDEO = 'video';

    /**
     * Constant message status delivered. 
     */
    const MSG_STATUS_DELIVERED = 'Delivered';

    /**
     * Constant message status on-transit. 
     */
    const MSG_STATUS_TRANSIT = 'Transit';

    /**
     * Data from 'messages' database table. 
     * @access private
     * @var array
     */
    private $data;

    /**
     * Attachment associated with this message.
     * @access private
     * @var FileUpload|null
     */
    private $file;

    /**
     * Constructor. 
     *
     * @param array $data
     */
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

    private function getReceivedTime(bool $isCrew) : string
    {
        if($isCrew)
        {
            return $this->data['recv_time_hab'];
        }
        return $this->data['recv_time_mcc'];
    }

    /**
     * Get the message status (delivered or transit) from the perspective of 
     * the person receiving the message.
     *
     * @return string
     */
    private function getMsgStatus() : string
    {
        // Get current time. 
        $time = new DelayTime();

        // Get receive time.
        $recvTime = $this->getReceivedTime(!$this->data['is_crew']);

        // Assign and return the status
        $ret = self::MSG_STATUS_DELIVERED;
        if($recvTime > $time->getTime())
        {
            $ret = self::MSG_STATUS_TRANSIT;
        }
        return $ret;
    }

    /**
     * Return associative array with message contents to display on the chat applicaiton.
     *
     * @param User $userPerspective Format message data from perspective of logged in user.
     * @param boolean $remoteDest Format message for remote destination. 
     * @return array Associative array of message parameters for display. 
     */
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
            'remoteDest'       => $remoteDest,
        );

        // Add received expected received time by all participants in the conversation.
        if(boolval($this->data['is_crew']) == $remoteDest)
        {
            $msgData['recv_time'] = $msgData['recv_time_mcc'];
        }
        else
        {
            $msgData['recv_time'] = $msgData['recv_time_hab'];
        }

        // If not null, add the details on the file attachment. 
        if($this->data['type'] != self::TEXT && $this->file != null && $this->file->exists())
        {
            $msgData['filename'] = $this->file->original_name;
            $msgData['filesize'] = $this->file->getSize();
            $msgData['type'] = $this->file->getTemplateType();
            $msgData['mime_type'] = $this->file->mime_type;
        }

        // Set the format of who authored the message. 
        if($userPerspective->user_id == $this->data['user_id'])
        {
            // Current user
            $msgData['source'] = 'usr';
        }
        elseif($this->data['is_crew'])
        {
            // Someone elese in the habitat
            $msgData['source'] = 'hab';
        }
        else
        {
            // Someone else in MCC
            $msgData['source'] = 'mcc';
        }

        return $msgData;
    }
}

?>