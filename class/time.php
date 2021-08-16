<?php

class DelayTime
{
    const DATE_FORMAT = 'Y-m-d H:i:s';
    private static $epochMet = 0;
    private static $timezone = '';
    private $ts = 0;

    public function __construct(string $datetime = 'now')
    {
        global $mission;
        
        if(self::$epochMet == null)
        {
            self::$timezone = new DateTimeZone($mission['timezone']);
            $epoch = new DateTime($mission['time_epoch'], self::$timezone);
            self::$epochMet = $epoch->getTimestamp();
        }

        $time = new DateTime($datetime, self::$timezone);
        $this->ts = $time->getTimestamp();
    }

    public static function getEpoch() : string
    {
        global $mission;
        $epoch = new DateTime($mission['time_epoch'], new DateTimeZone($mission['timezone']));
        return $epoch->getTimestamp();
    }

    // Return minutes for offset
    public static function getTimezoneOffset() : int 
    {   
        global $mission;
        $met = new DateTime('now', new DateTimeZone($mission['timezone']));
        return $met->format('Z');
    }

    public function getTimeUTC() : string 
    {
        $time = new DateTime();
        $time->setTimestamp($this->ts);
        $time->setTimezone(new DateTimeZone("UTC"));
        return $time->format(self::DATE_FORMAT.'P');
    }

    public function getTime(bool $mccFormat = true, bool $withDelay = false, bool $withTz = false) : string
    {
        $timeStr = '';
        $delay = ($withDelay) ? Delay::getInstance()->getDelay() : 0;

        if($mccFormat)
        {
            $time = new DateTime();
            $time->setTimestamp($this->ts + $delay);
            $time->setTimezone(self::$timezone);
            $timeStr = $time->format(self::DATE_FORMAT.($withTz ? 'P' : ''));
        }
        else
        {
            $timeStr = $this->formatForHab($this->ts - self::$epochMet + $delay);;
        }

        return $timeStr;
    }

    private function formatForHab(int $met) : string 
    {
        global $mission;
        
        // Format time diff
        $day = floor($met / $mission['time_sec_per_day']);
        $hrs = floor(($met - $day * $mission['time_sec_per_day']) / 3600);
        $min = floor(($met - $day * $mission['time_sec_per_day'] - $hrs * 3600) / 60);
        $sec = floor($met - $day * $mission['time_sec_per_day'] - $hrs * 3600 - $min * 60);

        $hrsStr = strlen($hrs.'') < 2 ? '0'.$hrs : $hrs;
        $minStr = strlen($min.'') < 2 ? '0'.$min : $min;
        $secStr = strlen($sec.'') < 2 ? '0'.$sec : $sec;

        return $mission['time_day'].'-'.$day.' '.$hrsStr.':'.$minStr.':'.$secStr;
    }
    
}
/*
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
*/

?>