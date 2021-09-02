<?php

class DelayTime
{
    const DATE_FORMAT = 'Y-m-d H:i:s';
    private static $epochMet = 0;
    private $ts = 0;

    // Assumes all timestamps are provided in UTC format. 
    public function __construct(string $datetime = 'now', string $timezone = 'UTC')
    {
        $mission = MissionConfig::getInstance();

        if(self::$epochMet == null)
        {
            $epoch = new DateTime($mission->date_start, new DateTimeZone('UTC'));
            self::$epochMet = $epoch->getTimestamp();
        }

        $time = new DateTime($datetime, new DateTimeZone($timezone));
        $this->ts = $time->getTimestamp();
    }

    public static function convertTimestampTimezone(string $timestamp, string $fromTz, string $toTz) : string
    {
        $ts = new DateTime($timestamp, new DateTimeZone($fromTz));
        $ts->setTimezone(new DateTimeZone($toTz));
        return $ts->format(self::DATE_FORMAT);
    }

    public static function getEpochUTC() : string
    {
        $mission = MissionConfig::getInstance();
        $epoch = new DateTime($mission->date_start, new DateTimeZone('UTC'));
        return $epoch->getTimestamp();
    }

    // Return minutes for offset to UTC
    public static function getTimezoneOffsetfromUTC(bool $mccTz=true) : int 
    {   
        $mission = MissionConfig::getInstance();
        $userTz = $mccTz ? $mission->mcc_timezone : $mission->hab_timezone;
        $met = new DateTime('now', new DateTimeZone($userTz));
        return $met->format('Z');
    }

    // Return UTC time with/without delay 
    public function getTime(bool $withDelay = false) : string
    {
        $timeStr = '';
        $delay = ($withDelay) ? Delay::getInstance()->getDelay() : 0;

        $time = new DateTime();
        $time->setTimestamp($this->ts + $delay);
        $time->setTimezone(new DateTimeZone("UTC"));
        $timeStr = $time->format(self::DATE_FORMAT);
        
        return $timeStr;
    }

    public function getMet() : int
    {
        return $this->ts - self::getEpochUTC();
    }
}

?>