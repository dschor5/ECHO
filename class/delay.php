<?php 

class Delay
{
    private static $instance = null;
    private $currDelay = 0;
    const SPEED_OF_LIGHT_KM_P_SEC = 299.792458;
    const SEC_PER_MIN = 60;
    const SEC_PER_HOUR = 60 * 60;


    private function __construct()
    {
        $this->currDelay = 300;
    }

    public static function getInstance()
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
        return $this->currDelay;
    }

    public function getDelayStr(): string
    {
        $delayStr = '';
        $hrs = intdiv($this->currDelay, self::SEC_PER_HOUR);
        $min = intdiv($this->currDelay - $hrs * self::SEC_PER_HOUR, self::SEC_PER_MIN);
        $sec = $this->currDelay - $hrs * self::SEC_PER_HOUR - $min * self::SEC_PER_MIN;

        if($hrs > 0)
        {
            $delayStr .= number_format($hrs).'hr ';
        }
        if($min > 0)
        {
            $delayStr .= number_format($min).'min ';
        }

        return $delayStr.number_format($sec, 2).'sec';;
    }

    public function getDistance(): float
    {
        return $this->currDelay * self::SPEED_OF_LIGHT_KM_P_SEC;
    }

    public function getDistanceStr(): string
    {
        return number_format($this->getDistance(),2)." km"; 
    }

    public function getDelayString(): string
    {
        return ''.$this;
    }
}

?>