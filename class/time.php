<?php

class DelayTime
{
    const DATE_FORMAT = 'Y-m-d H:i:s';
    private static $epochMet = 0;
    private static $timezone = '';
    private $ts = 0;

    public function __construct(string $datetime = 'now', bool $mccTz=true)
    {
        $mission = MissionConfig::getInstance();

        $timezone = $mccTz ? $mission->mcc_timezone : $mission->hab_timezone;
        
        if(self::$epochMet == null)
        {
            self::$timezone = new DateTimeZone($timezone);
            $epoch = new DateTime($mission->date_start, self::$timezone);
            self::$epochMet = $epoch->getTimestamp();
        }

        $time = new DateTime($datetime, self::$timezone);
        $this->ts = $time->getTimestamp();
    }

    public static function getEpochUTC() : string
    {
        $mission = MissionConfig::getInstance();
        $epoch = new DateTime($mission->date_start, new DateTimeZone('UTC'));
        return $epoch->getTimestamp();
    }

    // Return minutes for offset to UTC
    public static function getTimezoneOffset(bool $mccTz=true) : int 
    {   
        $mission = MissionConfig::getInstance();
        $timezone = $mccTz ? $mission->mcc_timezone : $mission->hab_timezone;
        $met = new DateTime('now', new DateTimeZone($timezone));
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
        $mission = array('time_sec_per_day'=>24*60*60, 'time_day' => 'Mission Sol');
        
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

?>