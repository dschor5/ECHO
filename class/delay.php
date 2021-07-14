<?php 

class Delay
{
    private static $instance = null;

    private function __construct()
    {

    }

    public function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new Delay();
        }
        return self::$instance;
    }

    public function readConfig(string $filename): bool
    {
        return true;
    }

    public function getDelay(): int
    {
        return 0;
    }

    public function getDelayString(): string
    {
        return ''.$this
    }
}

?>