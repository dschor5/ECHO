<?php

/**
 * DelayTime object is a dual purpose class where one can:
 *  1. Create/manipulate a DelayTime object for a particular timestamp. 
 *     No matter what timezone is used to create the object, internally
 *     the object stores the UTC timestamp. 
 *  2. Use static functions to convert timezones or get constants. 
 * 
 * @link https://github.com/dschor5/AnalogDelaySite
 */
class DelayTime
{
    /**
     * MySQL datetime format. 
     * @access private
     * @var string
     */
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Javascript datetime format
     * @access private
     * @var string
     */
    const DATE_FORMAT_JS = 'Y-m-d\TH:i:s.000\Z';

    /**
     * Epoch Mission Elapsed Time (MET) calculated since the start of the 
     * mission defined in mission_config.date_start. 
     * @access private
     * @var int
     */
    private static $epochMet = 0;

    /**
     * Timestamp being manupulated by $this object in UTC timezone.
     */
    private $ts = 0;

    // Assumes all timestamps are provided in UTC format. 

    /**
     * Constructor. Builds a timestamp for a specific time and timezone. 
     * By default, the timestamp will be constructed for the current UTC time. 
     * 
     * @param string $datetime Datetime string in DATE_FORMAT. 
     * @param string $timezone Timezone name. 
     */
    public function __construct(string $datetime = 'now', string $timezone = 'UTC')
    {
        $mission = MissionConfig::getInstance();

        // Initialize epoch for calculations. 
        if(self::$epochMet == null)
        {
            $epoch = new DateTime($mission->date_start, new DateTimeZone('UTC'));
            self::$epochMet = $epoch->getTimestamp();
        }

        // Create a DateTime object for the given timezone and then get the 
        // UTC equivalent time to store within the object. 
        $time = new DateTime($datetime, new DateTimeZone($timezone));
        $this->ts = $time->getTimestamp();
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

    public static function convertTsForJs(string $tsStr) : string
    {
        $ts = new DateTime($tsStr);
        return $ts->format(self::DATE_FORMAT_JS);
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

}

?>