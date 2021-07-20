<?php

class TimeKeeper
{
    private static $instance = null;
    private $epoch;
    private $secPerDay;
    private $dayName;

    private function __construct()
    {
        $this->config(date_format(new DateTime(), 'Y-m-d H:i:s'), 24*60*60, 'Day');
    }

    public static function getInstance(): TimeKeeper
    {
        if(self::$instance == null)
        {
            self::$instance = new TimeKeeper();
        }

        return self::$instance;
    }

    public function config(string $epoch, int $secPerDay, string $dayName)
    {
        $temp = new DateTime($epoch);
        $this->epoch = $temp->getTimestamp();
        $this->secPerDay = $secPerDay;
        $this->dayName = $dayName;
    }

    public function getHabTimestamp(DateTime $d = null) : string
    {
        if($d == null)
        {
            $d = new DateTime();
        }

        return $d->getTimestamp() - $this->epoch;
    }

    public function getMccTimestamp(DateTime $d = null) : string 
    {
        if($d == null)
        {
            $d = new DateTime();
        }

        return $d->getTimestamp();
    }

    public function getMccTimeStr(Datetime $d = null) : string
    {
        return date_format($this->getMccTimestamp($d), 'Y-m-d H:i:s');
    }

    public function getHabTimeStr(DateTime $d = null) : string
    {
        $timeDiff = $this->getHabTimestamp();
        
        // Format time diff
        $day = floor($timeDiff / $this->secPerDay);
        $hrs = floor(($timeDiff - $day * $this->secPerDay) / 3600);
        $min = floor(($timeDiff - $day * $this->secPerDay - $hrs * 3600) / 60);
        $sec = floor($timeDiff - $day * $this->secPerDay - $hrs * 3600 - $min * 60);

        $hrsStr = strlen($hrs.'') < 2 ? '0'.$hrs : $hrs;
        $minStr = strlen($min.'') < 2 ? '0'.$min : $min;
        $secStr = strlen($sec.'') < 2 ? '0'.$sec : $sec;

        return $this->dayName.'-'.$day.' '.$hrsStr.':'.$minStr.':'.$secStr;
    }
}

?>