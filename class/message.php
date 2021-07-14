<?php

class Message
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
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