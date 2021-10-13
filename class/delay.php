<?php 

/**
 * Singleton Delay object used to parse/interpret the user configurable comms delay.
 * 
 * Implementation Notes:
 * - This class does not validate the 
 * 
 * @link https://github.com/dschor5/AnalogDelaySite
 */
class Delay
{
    /**
     * Singleton instance of Delay object.
     * @access private
     * @var Delay
     */
    private static $instance = null;

    /**
     * Current delay expressed in seconds.
     * @access private
     * @var double
     */
    private $currDelay = 0;

    /**
     * Constant speed of light in km/s to calculate distance from delay. 
     * Source: https://en.wikipedia.org/wiki/Speed_of_light
     * @access private
     * @var double
     */
    const SPEED_OF_LIGHT_KM_P_SEC = 299792.458;

    /**
     * Constant number of seconds per minute.
     * @access private
     * @var int
     */
    const SEC_PER_MIN = 60;

    /**
     * Constant number of seconds per hour. 
     * @access private
     * @var int
     */
    const SEC_PER_HOUR = 3600;

    /** 
     * Private constructor that initializes to no comms delay. 
     */
    private function __construct()
    {
        $this->currDelay = 0;
    }

    /**
     * Returns singleton instance of this object. 
     * 
     * @return Delay object
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new Delay();
        }
        return self::$instance;
    }

    /**
     * Get the current communication delay in seconds. 
     * 
     * Implementation notes:
     * - Parses the database entry describing the delays. 
     * - Finds what delay equation to apply at this time. 
     * - Evaluates the delay expression. 
     * 
     * @return float Delay in seconds.
     */
    public function getDelay(): float
    {
        // Get the current mission configuration and parse the delay field. 
        $mission = MissionConfig::getInstance();
        $config = json_decode($mission->delay_config, true);

        // Add dummy delay configs before/after mission to make it easier to traverse the array.
        array_unshift($config, array('ts'=>'2000-00-00 00:00:00', 'eq'=>'0'));
        array_push($config, array('ts'=>'2100-01-01 00:00:00', 'eq'=>'0'));

        // Find which entry in the delay configuration applies to the curent time. 
        $i = 1;
        while(!(strtotime($config[$i]['ts']) < time() && time() <= strtotime($config[$i+1]['ts'])) && $i < count($config)-1)
        {
            $i++;
        }

        // Evaluate the current delay assuming the equation was already validated when saved to the database. 
        try 
        {
            // Replace any instance of "time" in the equation with the current MET. 
            // In other words, delay = f(time). 
            $metObj = new DelayTime();
            $config[$i]['eq'] = preg_replace('/time/', $metObj->getMet(), $config[$i]['eq']);
            eval('$this->currDelay = '.$config[$i]['eq'].';');
            // Ensure the delay >= 0. 
            $this->currDelay = max(0, $this->currDelay);
        } 
        catch (Exception $e) 
        {
            Logger::warning("Could not parse equation", $config[$i]['eq']);
            $this->currDelay = 0;
        }
        return $this->currDelay;
    }

    /**
     * Compare entries in a piece-wise delay configuration for sorting purposes. 
     * Returns 0 if the two timestamps are equal, -1 if $a < $b, and 1 otherwise.
     * 
     * @param array $a Array ('ts' => timestamp, 'eq' => equation).
     * @param array $b Array ('ts' => timestamp, 'eq' => equation).
     * @return int Result of comparison
     */
    public static function sortAutoDelay(array $a, array $b) : int
    {
        // Create a DateTime object and get the unix timestamp associated for each param.
        $aObj = new DateTime($a['ts']);
        $aTs = $aObj->getTimestamp();        
        $bObj = new DateTime($b['ts']);
        $bTs = $bObj->getTimestamp();

        // Compare the timestamps. 
        if($aTs == $bTs)
        {
            return 0;
        }
        return ($aTs < $bTs) ? -1 : 1;
    }

    /**
     * Returns a human-readble string representing the current comms delay. 
     * 
     * @return string Representation of current delay. 
     */
    public function getDelayStr(): string
    {
        // Get the current delay
        $currDelay = $this->getDelay();
        
        // Output string
        $delayStr = '';

        // Get the number of hours, minutes, and seconds for the delay.
        $hrs = intdiv($currDelay, self::SEC_PER_HOUR);
        $min = intdiv($currDelay - $hrs * self::SEC_PER_HOUR, self::SEC_PER_MIN);
        $sec = $this->currDelay - $hrs * self::SEC_PER_HOUR - $min * self::SEC_PER_MIN;

        // Format the output
        if($hrs > 0)
        {
            $delayStr .= number_format($hrs).'hr ';
        }
        if($hrs > 0 || $min > 0)
        {
            $delayStr .= number_format($min).'min ';
        }

        return $delayStr.number_format($sec, 2).'sec';;
    }

    /**
     * Get the distance associated with the current comms delay in km. 
     * 
     * @return float Distance between MCC and HAB in km. 
     */
    public function getDistance(): float
    {
        return $this->getDelay() * self::SPEED_OF_LIGHT_KM_P_SEC;
    }

    /**
     * Get the distance associated with the current comms delay as 
     * a string with units in km. 
     * 
     * @return string Distance between MCC and HAB in km.
     */
    public function getDistanceStr(): string
    {
        return number_format($this->getDistance(),2)." km"; 
    }
}

?>
