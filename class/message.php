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
                array_flip('message_id', 'server_name', 'original_name', 'mime_type'))
            );
        }
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

    private function getTime(string $name) : string
    {
        $time = new DelayTime($this->data[$name]);
        return $time->getTime(true, false, true);
    }

    private function getTimeUTC(string $name) : string
    {
        $time = new DelayTime($this->data[$name]);
        return $time->getTimeUtc();
    }

    public function compileJson(User &$userPerspective, bool $remoteStatus=false) : string
    {
        $msgData = array(
            'message_id'       => $this->data['message_id'],
            'user_id'          => $this->data['user_id'],
            'author'           => $this->data['alias'],
            'message'          => $this->data['text'],
            'sent_time'        => $this->getTime('sent_time'),
            'recv_time_mcc'    => $this->getTime('recv_time_mcc'),
            'recv_time_hab'    => $this->getTime('recv_time_hab'),
            'delivered_status' => $this->getMsgStatus(),
        );

        // If authored by this user
        if($userPerspective->getId() == $this->data['user_id'])
        {
            $msgData['type'] = 'usr';
        }
        // Else authored by someone else on the habitat
        elseif($this->data['is_crew'])
        {
            $msgData['type'] = 'hab';
        }
        // Or authored by someone else in MCC. 
        else
        {
            $msgData['type'] = 'mcc';
        }

        return json_encode($msgData);
    }

    public static function compileEmptyMsgTemplate(string $template) : string
    {
        $templateData = array(
            // Message template
            '/%message-id%/'       => '',
            '/%user-id%/'          => '',
            '/%author%/'           => '',
            '/%message%/'          => '',
            '/%sent-time%/'        => '',
            '/%recv-time-mcc%/'    => '',
            '/%recv-time-hab%/'    => '',
            '/%delivered-status%/' => '',

            // Content template

        );

        return Main::loadTemplate('modules/'.$template, $templateData);
    }

    private function getRecvTime(bool $isCrew, bool $remoteStatus) : string
    {
        return "";
    }

    private function compileContentHtml() : string 
    {
        if($this->data['type'] == self::TEXT)
        {
            $content = nl2br($this->data['text']);
        }
        else if($this->file->exists())
        {
            $templateType = $this->file->getTemplateType();
            $templateFile = 'modules/chat-msg-'.$templateType.'.txt';
            return $templateFile;
            $templateData = array(
                '/%filename%/' => $this->file->getOriginalName(),
                '/%filesize%/' => $this->file->getSize(),
            );
            $content = Main::loadTemplate($templateFile, $templateData);
        }
        else
        {
            $content = 'File "'.$file->getOriginalName().'" was not found.';
        }

        return $content;
    }

    public function compileHtml(User &$userPerspective, bool $remoteStatus=false) : string 
    {
        global $config;

        $msgData = array(
            '/%message-id%/'       => $this->data['message_id'],
            '/%user-id%/'          => $this->data['user_id'],
            '/%author%/'           => $this->data['alias'],
            '/%message%/'          => $this->compileContentHtml(),
            '/%sent-time%/'        => $this->getTime('sent_time'),
            '/%recv-time-mcc%/'    => $this->getTime('recv_time_mcc'),
            '/%recv-time-hab%/'    => $this->getTime('recv_time_hab'),
            '/%recv-time%/'        => $this->getRecvTime($userPerspective->isCrew(), $remoteStatus),
            '/%delivered-status%/' => $this->getMsgStatus(),
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