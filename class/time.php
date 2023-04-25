<?php

/**
 * DelayTime object is a dual purpose class where one can:
 *  1. Create/manipulate a DelayTime object for a particular timestamp. 
 *     No matter what timezone is used to create the object, internally
 *     the object stores the UTC timestamp. 
 *  2. Use static functions to convert timezones or get constants. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class DelayTime
{
    /**
     * MySQL datetime format. 
     * @access private
     * @var string
     */
    const DATE_FORMAT = 'Y-m-d H:i:s.v';

    /**
     * Javascript datetime format
     * @access private
     * @var string
     */
    const DATE_FORMAT_JS = 'Y-m-d\TH:i:s.v\Z';

    /**
     * Filename compatible datetime format
     * @access private
     * @var string
     */
    const DATE_FORMAT_FILE = 'Y-m-d\TH-i-s';
    
    /**
     * Regex to validate a date string in "YYYY-MM-DD HH:MM:SS" format.
     * @access public
     * @var string 
     */
    const DATE_FORMAT_REGEX   = '/^[\d]{4}-[\d]{2}-[\d]{2}\s[\d]{2}:[\d]{2}:[\d]{2}$/';
    const DATE_FORMAT_MS_REGEX = '/^[\d]{4}-[\d]{2}-[\d]{2}T[\d]{2}:[\d]{2}:[\d]{2}.[\d]{3}Z$/';

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

    /**
     * Returns the current UTC timestamp with/without the delay added 
     * in the format requested by the user. 
     * 
     * @param bool $withDelay If TRUE, add the current delay to the timestamp. 
     * @param string $format  Format for string representation of the timestamp. 
     *                        Defaults to DelayTime::DATE_FORMAT for MySQL. 
     * @return string Formatted UTC timestamp. 
     */
    public function getTime(bool $withDelay = false, $format=DelayTime::DATE_FORMAT) : string
    {
        // Delay to add to the timestamp. 
        $delay = ($withDelay) ? Delay::getInstance()->getDelay() : 0;

        // Create a new timestamp
        $time = new DateTime();
        $time->setTimestamp(intval($this->ts + $delay));
        $time->setTimezone(new DateTimeZone("UTC"));

        // Format and return the timestamp
        return $time->format($format);
    }

    /**
     * Get the Mission Elapsed Time (MET) in seconds for this timestamp. 
     * This is calculated by looking at the current time minus the mission epoch. 
     * 
     * @return int MET in seconds. 
     */
    public function getMet() : int
    {
        return $this->ts - self::getStartTimeUTC();
    }

    /**
     * Get the UNIT timestamp for the current date object.
     *
     * @return int 
     **/
    public function getTimestamp() : int
    {
        return $this->ts;
    }

    /**
     * Static function to convert a timestamp string into a JavaScript compatible format. 
     * 
     * @param string $tsStr Timestamp. Generally expects the MySQL format (YYYY-MM-DD HH:MM:SS)
     * @return string Javascript compatible timestamp
     */
    public static function convertTsForJs(string $tsStr) : string
    {
        $ts = new DateTime($tsStr);
        return $ts->format(self::DATE_FORMAT_JS);
    } 

    /**
     * Static function that converts a timestamp from 
     * a given timezone to a different timezone. 
     * 
     * @param string $timestamp Date and time in DATE_FORMAT. 
     * @param string $fromTz Name of timezone to interpret timestamp. 
     * @param string $toTz Name of timezone to convert the timestamp to. 
     * @param string $format Fomrat for string output. Defaults to DATE_FORMAT.
     * @return string Timestamp in new timezone and format.
     */
    public static function convertTimestampTimezone(string $timestamp, string $fromTz, string $toTz, string $format=DelayTime::DATE_FORMAT) : string
    {
        $ts = new DateTime($timestamp, new DateTimeZone($fromTz));
        $ts->setTimezone(new DateTimeZone($toTz));
        return $ts->format($format);
    }

    /**
     * Static function that returns the mission epoch in UTC. 
     * @return int Unix timestamp for mission epoch in UTC.
     */
    public static function getStartTimeUTC() : int
    {
        $mission = MissionConfig::getInstance();
        $t = new DateTime($mission->date_start, new DateTimeZone('UTC'));
        return $t->getTimestamp();
    }

    /**
     * Static function that returns the mission end time in UTC. 
     * @return int Unix timestamp for mission end time in UTC.
     */
    public static function getEndTimeUTC() : int
    {
        $mission = MissionConfig::getInstance();
        $t = new DateTime($mission->date_end, new DateTimeZone('UTC'));
        return $t->getTimestamp();
    }    

    /**
     * Static function that returns the timezone offset in seconds for MCC or the HAB. 
     * Per PHP documentation, timezones west of UTC are negative. 
     * 
     * @param bool $mccTz True to get MCC offset. False to get HAB offset. 
     * @return int Timezone offset in seconds.
     */
    public static function getTimezoneOffsetfromUTC(bool $mccTz=true) : int 
    {   
        $mission = MissionConfig::getInstance();
        $userTz = $mccTz ? $mission->mcc_timezone : $mission->hab_timezone;
        $met = new DateTime('now', new DateTimeZone($userTz));
        return $met->format('Z');
    }
}

?>