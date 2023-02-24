<?php 

/**
 * Delay object used to parse/interpret the user configurable comms delay.
 * 
 * The field 'mission_config.delay_type' selects whether to use:
 * --> Manual delay -       Can be changed anytime before, during, or after a 
 *                          mission and takes effect immediately.
 *
 * --> Automatic delay -    Automatic delays allow Administrators to define 
 *                          delays as a piecewise function of time. Each 
 *                          piecewise component is defined by an equation 
 *                          and a timestamp when that delay will activate.
 *
 * --> Current Mars delay - Applies the current delay assuming direct 
 *                          point-to-point contact with Mars ignoring 
 *                          interference from the Sun, use of the Deep 
 *                          Space Network, planet rotation, etc. The delays 
 *                          were simulated on 4hr intervals until 2040 using 
 *                          the JPL DE440S Ephemeris Data Set.
 *
 * The manual and automatic delays are stored in the 'mission_config.delay_config' 
 * field as a JSON encoded array where each entry contains a timestamp and 
 * expression to calculate the delay. For instance, the following example 
 * says that for the first minute in 2021, the delay is 0sec, but after that 
 * it will increase to 10sec. 
 *      [
 *          {'ts':'2021-01-01 00:00:00', 'eq':0}, 
 *          {'ts':'2021-01-01 00:01:00', 'eq':10}, 
 *      ]
 * 
 * Whereas the current Mars delay is read from a file containing the distance
 * between Earth and Mars computed every four hours from 2020-2040.
 * 
 * Implementation Notes:
 * - Singleton implementation.
 * - This class does not validate the delay expressions. It is assumed that
 *   the validation is done when saving the settings in the admin module. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class Delay
{
    /**
     * Singleton instance of Delay object.
     * @access private
     * @var Object
     */
    private static $instance = null;

    /**
     * Current delay expressed in seconds.
     * @access private
     * @var double
     */
    private $currDelay = 0;

    /**
     * Unit timestamp of the last time the delay was calculated. 
     * Used for caching/refreshing the delay. 
     * @access private
     * @var double
     */
    private $lastCheck = 0;

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
     * Delay cache timeout in seconds. 
     * Default value of 1sec was used to match the refresh rate 
     * for the time display on the chat application.
     * @access private
     * @var double
     */
    const CACHE_TIMEOUT = 1.0;

    /**
     * Constants matching delay_type. 
     * @access private
     * @var string
     */
    const MANUAL = 'manual';
    const TIMED  = 'timed';
    const MARS   = 'mars';

    /** 
     * Private constructor that initializes to no comms delay. 
     */
    private function __construct()
    {
        $this->currDelay = 0;
        $this->lastCheck = 0;
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
     * Get current onw-way light time communication delay based on the 
     * selected delay_type.
     *
     * @return float Delay in sec.
     **/
    public function getDelay() : float
    {
        // If the cached valud is still vaid return it. 
        // The cache is used to avoid extra queries/calculations for 
        // the stream of data that sends the current delay to the user GUI.
        if(microtime(true) - $this->lastCheck < Delay::CACHE_TIMEOUT)
        {
            return $this->currDelay;
        }

        // Update cache refresh timestamp
        $this->lastCheck = microtime(true);

        // Get current delay based on the delay type.
        $mission = MissionConfig::getInstance();
        if($mission->delay_type == Delay::MARS)
        {
            $this->currDelay = $this->getMarsDelay();
        }
        else
        {
            $this->currDelay = $this->getManualOrTimedDelay();
        }

        return $this->currDelay;
    }

    /** 
     * Get the current Mars delay from a file containing simulated 
     * delays on 4hr intervals between 2020 and 2024 using the 
     * JPL DE440S Ephemeris Data Set. 
     *
     * The csv file contains two fixed width columns with:
     * - Timestamp (YYYY-MM-DD HH:MM:SS) [19 chars]
     * - Delay in sec                    []
     * Thus, rather than reading through the entire file looking for a 
     * particular timestamp, this function fast-forwards X bytes 
     * to find the appropriate delay for the current UTC time.
     *
     * @return float 
     **/
    private function getMarsDelay() : float
    {
        $delay = 0.0;

        global $config;
        global $server;
        $filename = $server['host_address'].$config['logs_dir'].'/'.$config['delay_mars_file'];

        // Open file containing delay
        $fp = fopen($filename, 'r');
        if($fp === false)
        {
            Logger::warning('Delay::getMarsDelay() failed to open file.');
            return $delay;
        }

        // Read first line to extract starting epoch
        $line = fgets($fp);
        $lineLen = ftell($fp);
        if($line === false)
        {
            Logger::warning('Delay::getMarsDelay() failed to read epoch line.');
            fclose($fp);
            return $delay;
        }

        list($epochStr, ) = explode(',', $line, 2);
        $epochObj = new DateTime($epochStr, new DateTimeZone('UTC'));
        $epoch = $epochObj->getTimestamp();
    
        // Read second line to extract time jumps
        fseek($fp, 46);
        $line = fgets($fp);
        if($line === false)
        {
            Logger::warning('Delay::getMarsDelay() failed to read time jump line.');
            fclose($fp);
            return $delay;
        }
        
        list($timeJumpStr, ) = explode(',', $line, 2);
        $timeJumpObj = new DateTime($timeJumpStr, new DateTimeZone('UTC'));
        $timeJump = $timeJumpObj->getTimestamp() - $epoch;
        
        // Get current time to determine offset in file
        $nowObj = new DateTime('now', new DateTimeZone('UTC'));
        $now = $nowObj->getTimestamp() - $epoch;

        // Seek offset
        fseek($fp, 0); // Reset file
        $offset = max(0, intdiv($now, $timeJump) );
        if(fseek($fp, $lineLen * $offset) == -1)
        {
            Logger::warning('Delay::getMarsDelay() seek past end of file.');
            fclose($fp);
            return $delay;
        }
        
        // Read line after byte offset. 
        $line = fgets($fp);
        if($line === false)
        {
            Logger::warning('Delay::getMarsDelay() failed to read time line.');
            fclose($fp);
            return $delay;
        }

        // Read next line and get delay
        list(, , $delayStr) = explode(',', $line, 4);
        
        // Close file
        fclose($fp);

        return floatval($delayStr);
    }

    /**
     * Get the current communication delay in seconds. 
     * 
     * Implementation notes:
     * - Caches value to avoid finding the curr delay and 
     *   evaluating the expression multiple times per page load. 
     * - Parses the database entry describing the delays. 
     * - Prepend and append dummy entries to the list
     *   [
     *      {'ts':'2000-01-01 00:00:00', 'eq':0},  <-- DUMMY ENTRY IN THE PAST
     *      {'ts':'2021-01-01 00:00:00', 'eq':0}, 
     *      {'ts':'2021-01-01 00:01:00', 'eq':10},
     *      {'ts':'2100-01-01 00:00:00', 'eq':0},  <-- DUMMY ENTRY IN THE FUTURE
     *   ]
     * - Find index into the array where:
     *      list[$i]['ts'] < currentTime() < list[$i+1]['ts']
     * - Evaluates the delay expression. 
     * 
     * @return float Delay in seconds.
     */
    private function getManualOrTimedDelay(): float
    {
        // Get the current mission configuration and parse the delay field. 
        $mission = MissionConfig::getInstance();
        $config = json_decode($mission->delay_config, true);

        // Add dummy delay configs before/after mission to make it easier to traverse the array.
        array_unshift($config, array('ts'=>'2000-00-00 00:00:00', 'eq'=>'0'));
        array_push($config, array('ts'=>'2100-01-01 00:00:00', 'eq'=>'0'));

        // Find which entry in the delay configuration applies to the curent time. 
        $i = 0;
        while(!(strtotime($config[$i]['ts']) < time() && 
            time() <= strtotime($config[$i+1]['ts'])) && 
            $i < count($config)-1)
        {
            $i++;
        }

        // Evaluate the current delay assuming the equation was already validated when saved to the database. 
        try 
        {
            // Replace any instance of "time" in the equation with the current MET. 
            // In other words, delay = f(time). 
            $currDelay = 0.0;
            $metObj = new DelayTime();
            $config[$i]['eq'] = preg_replace(
                array(
                    '/time/i', 
                    '/\^/'
                ),
                array(
                    '('.($metObj->getMet()).')',
                    '**'
                ),
                $config[$i]['eq']
            );
            eval('$currDelay = '.$config[$i]['eq'].';');
            // Ensure the delay >= 0. 
            $currDelay = max(0.0, floatval($currDelay));
        } 
        catch (Exception $e) 
        {
            Logger::warning("Could not parse equation", $config[$i]['eq']);
            $currDelay = 0.0;
        }
        return $currDelay;
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
        $hrs = intdiv(intval($currDelay), self::SEC_PER_HOUR);
        $min = intdiv(intval($currDelay - $hrs * self::SEC_PER_HOUR), self::SEC_PER_MIN);
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
