<?php

class TimeKeeper
{
    private static $instance = null;
    private $epoch;
    private $secPerDay;
    private $dayName;
    private $timezone;

    private function __construct()
    {
        global $mission;
        $this->config($mission['time_epoch'], $mission['timezone'], $mission['time_sec_per_day'], $mission['time_day']);
    }

    public static function getInstance(): TimeKeeper
    {
        if(self::$instance == null)
        {
            self::$instance = new TimeKeeper();
        }

        return self::$instance;
    }

    private function config(string $epoch, string $timezone, int $secPerDay, string $dayName)
    {
        $temp = new DateTime($epoch, new DateTimeZone($timezone));
        $this->epoch = $temp->getTimestamp();
        $this->secPerDay = $secPerDay;
        $this->dayName = $dayName;
        $this->timezone = $timezone;
    }

    public function getHabTimestamp(DateTime $d = null) : string
    {
        if($d == null)
        {
            $d = new DateTime();
            $d->setTimezone(new DateTimeZone($this->timezone));
        }

        return $d->getTimestamp() - $this->epoch;
    }

    public function getMccTimestamp(DateTime $d = null) : string 
    {
        if($d == null)
        {
            $d = new DateTime();
            $d->setTimezone(new DateTimeZone($this->timezone));
        }

        return $d->getTimestamp();
    }

    public function getMccTimeStr(Datetime $d = null) : string
    {
        $epoch = $this->getMccTimestamp($d);
        $date = new DateTime("@$epoch");
        $date->setTimezone(new DateTimeZone($this->timezone));
        return $date->format('Y-m-d H:i:s');
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