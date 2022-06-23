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
     * 
     * @access private
     * @var array
     */
    private $data;

    /**
     * Attachment associated with this message.
     * 
     * @access private
     * @var FileUpload|null
     */
    private $file;

    /**
     * Message Constructor. If the message contains an attachment, this will
     * also build the FileUpload object.
     *
     * @param array $data Row from 'messages' database table. 
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->file = null;

        // If the message contains an attachment, load teh corresponding fields. 
        if($this->data['type'] != self::TEXT)
        {
            $this->file = new FileUpload(
                array_intersect_key($this->data, 
                    array_flip(array('message_id', 'server_name', 'original_name', 'mime_type')))
            );
        }
    }

    /**
     * Accessor for Message fields. Returns value stored in the field $name 
     * or null if the field does not exist. 
     * 
     * @param string $name Name of field being requested. 
     * @return mixed Value contained by the field requested. 
     */
    public function __get($name)
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            $result = $this->data[$name];
        }
        else
        {
            Logger::warning('Message __get("'.$name.'")', $this->data);
        }

        return $result;
    }

    /**
     * Get the message received time.
     * 
     * @param bool $remoteDest True if sending to a remote destination.
     */
    private function getReceivedTime(bool $remoteDest) : string
    {
        $str = 'remoteDest='.($remoteDest?'true':'false').', '. 
               'is_crew='.($this->is_crew?'true':'false');
        Logger::error($str)
        return ($remoteDest xor $this->is_crew) ? $this->recv_time_mcc : $this->recv_time_hab;
    }

    /**
     * Get the message status (delivered or transit) from the perspective of 
     * the person receiving the message.
     *
     * @param bool $remoteDest True if sending to a remote destination.
     * @return string
     */
    private function getMsgStatus(bool $remoteDest) : string
    {
        // Get current time. 
        $time = new DelayTime();

        // Get receive time.
        $recvTime = $this->getReceivedTime($remoteDest);

        // Assign and return the status
        $ret = self::MSG_STATUS_DELIVERED;
        if($recvTime > $time->getTime())
        {
            $ret = self::MSG_STATUS_TRANSIT;
        }
        return $ret;
    }

    public function archiveMessage(ZipArchive &$zip, string $folder, array &$participants, bool $crewPerspective, string $tz) 
    {
        $perspective = $crewPerspective ? 'recv_time_hab' : 'recv_time_mcc';

        $msg = $this->compileMsgText();
        if($this->data['type'] != self::TEXT && $this->file != null && $this->file->exists())
        {
            $msg = $this->file->original_name.' ('.$this->file->getSize().')';

            $filepath = $this->file->getServerPath();
            $filename = $folder.'/'.sprintf('%05d', $this->message_id).'-'.$this->file->original_name;

            if(!$zip->addFile($filepath, $filename))
            {
                Logger::warning('message::archiveMessage failed to add file '.$filepath.' as '.$filename.'.');
                return false;
            }
        }

        return Main::loadTemplate('admin-data-save-msg.txt', 
            array('/%id%/'        => $this->data['message_id'],
                  '/%from-user%/' => $participants[$this->data['user_id']]['alias'],
                  '/%sent-time%/' => DelayTime::convertTimestampTimezone($this->data['sent_time'], 'UTC', $tz),
                  '/%recv-time-mcc%/' => DelayTime::convertTimestampTimezone($this->data[$perspective], 'UTC', $tz),
                  '/%recv-time-hab%/' => DelayTime::convertTimestampTimezone($this->data[$perspective], 'UTC', $tz),
                  '/%msg%/'       => $msg,
            ));
    }

    private function callback($match)
    {
        // Prepend http:// if no protocol specified
        $completeUrl = $match[1] ? $match[0] : "http://{$match[0]}";

        return '<a href="' . $completeUrl . '" target="_blank">'
            . $match[2] . $match[3] . $match[4] . '</a>';
    }

    private function compileMsgText() : string
    {
        $rexProtocol = '((https?://)|(ftp://))?';
        $rexDomain   = '((?:[-a-zA-Z0-9]{1,63}\.)+[-a-zA-Z0-9]{2,63}|(?:[0-9]{1,3}\.){3}[0-9]{1,3})';
        $rexPort     = '(:[0-9]{1,5})?';
        $rexPath     = '(/[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]*?)?';
        $rexQuery    = '(\?[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?';
        $rexFragment = '(#[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?';

        return preg_replace_callback("&\\b$rexProtocol$rexDomain$rexPort$rexPath$rexQuery$rexFragment(?=[?.!,;:\"]?(\s|$))&",
            array($this, 'callback'), htmlspecialchars($this->data['text']));
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
            'message'          => $this->compileMsgText(),
            'type'             => self::TEXT,
            'sent_time'        => DelayTime::convertTsForJs($this->data['sent_time']),
            'recv_time_mcc'    => DelayTime::convertTsForJs($this->data['recv_time_mcc']),
            'recv_time_hab'    => DelayTime::convertTsForJs($this->data['recv_time_hab']),
            'recv_time'        => $this->getReceivedTime($remoteDest),
            'delivered_status' => $this->getMsgStatus($remoteDest),
            'sent_from'        => $this->data['is_crew'],
            'remoteDest'       => $remoteDest,
        );
            
        
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