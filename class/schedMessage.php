<?php

/**
 * SchedMessage objects represent one message within the chat application.
 * Encapsulates 'sched_messages' row from database. 
 * 
 * Table Structure: 'sched_messages'
 * - sched_message_id           (int)       Global message id unique to this table.
 * - user_id                    (int)       User id who authored the message.
 * - conversation_id            (int)       Conversation where the message belongs.
 * - text                       (text)      Text stored in the message.
 * - from_crew                  (bool)      Boolean to indicate the message was sent from the crew (HAB)
 * - sched_send_time            (datetime)  UTC timestamp when to send message
 *
 * @link https://github.com/dschor5/ECHO
 */
class SchedMessage
{
    /**
     * Data from 'messages' database table. 
     * @access private
     * @var array
     */
    private $data;

    /**
     * SchedMEssage Constructor. 
     *
     * @param array $data Row from 'messages' database table. 
     */
    public function __construct(array $data)
    {
        $this->data = $data;
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

    public function getMsgArrayToSend()
    {
      $msgData = array(
         'user_id'         => $this->user_id,
         'from_crew'       => $this->from_crew,
         'conversation_id' => $this->conversation_id,
         'text'            => $this->text,
         'type'            => Message::TEXT,
     );

     return $msgData;
    }

}

?>
