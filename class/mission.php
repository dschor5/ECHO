<?php

/**
 * MissionConfig objects represent configuration parameters for the chat.
 * Encapsulates 'mission_config' row from database.
 * 
 * Table Structure: 'mission_config'
 * - name   (string)    Configuration field name
 * - value  (string)    Value for configuration field saved as a string.
 * - type   (string)    Variable type for parsing/interpreting config field.
 * 
 * Implementation Notes:
 * - Singleton implementation. 
 * - The class will automatically re-query the data if it has been more
 *   than QUERY_TIMEOUT seconds since the last database poll. This 
 *   ensures that changes (primarily to manual delay settings) are propagated
 *   throughout the site for active users. 
 * - Although not enforced/checked in this class, the assumption is that 
 *   all timestamps are in the UTC timezone. 
 */
class MissionConfig
{
    /**
     * Singleton instance of MissionConfig object.
     * @access private
     * @var Object
     */
    private static $instance = null;

    /**
     * Timestamp from last read from the database.
     * @access private
     * @var int
     */
    private $lastQueryTime;

    /**
     * Number of seconds between database queries to refresh mission config.
     * @access private
     * @var int
     */
    const QUERY_TIMEOUT = 30;

    /**
     * Data from 'mission_config' database table. 
     * @access private
     * @var array
     */
    private $data;

    /**
     * Private constructor. Loads mission config from database.
     */
    private function __construct()
    {
        // Force new query.
        $this->lastQueryTime = time() - 2 * self::QUERY_TIMEOUT;

        // Refresh data to load config from database.
        $this->refreshData();
    }

    /**
     * Returns singleton instance of this object. 
     * 
     * @return MissionConfig object
     */
    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new MissionConfig();
        }
        return self::$instance;
    }

    /**
     * Refresh configuration from database. 
     */
    private function refreshData()
    {
        // If it's been more than QUERY_TIMEOUT sec since the last
        // query, then refresh the data. 
        if($this->lastQueryTime + self::QUERY_TIMEOUT < time())
        {
            // Read configuration from the database
            $missionDao = MissionDao::getInstance();
            $this->data = $missionDao->readMissionConfig();

            // Update refresh timer
            $this->lastQueryTime = time();
        }
    }

    /**
     * Returns true if date_start < curr_time < date_end (all UTC).
     *
     * @return boolean
     */
    public function isMissionActive() : bool
    {
        $startDate = new DateTime($this->date_start, new DateTimeZone('UTC'));
        $endDate = new DateTime($this->date_end, new DateTimeZone('UTC'));
        $currTime = new DelayTime();

        return ($startDate->getTimestamp() <= $currTime->getTimestamp() && 
                $currTime->getTimestamp() <= $endDate->getTimestamp());
    }

    /**
     * Accessor for MissionConfig fields. Retuns parsed value stored
     * or null if the field is not found.
     * 
     * Unlike other accessors on the site, the mission configuration is 
     * key for the operation of the site. As such, any attempts to access
     * invalid fields will be treated as an error. 
     * 
     * @param string $name Name of field being requested. 
     * @throws Exception if an invalid field is requested. 
     * @return mixed Value contained by the field requested. 
     */
    public function __get(string $name)
    {
        $result = null;

        // Refresh data from database if needed.
        $this->refreshData();

        // If the key exists, use the type to parse the value. 
        if(array_key_exists($name, $this->data))
        {
            switch($this->data[$name]['type'])
            {
                case 'int': 
                    $result = intval($this->data[$name]['value']); 
                    break;
                case 'float':
                    $result = floatval($this->data[$name]['value']); 
                    break;
                case 'bool':
                    $result = boolval($this->data[$name]['value']); 
                    break;
                default:
                    $result = strval($this->data[$name]['value']);
            }
        }
        else
        {
            Logger::error('Invalid field "'.$name.'" requested from MissionConfig');
            throw new Exception();
        }

        return $result;
    }
}


?>