<?php

/**
 * User objects a user/account registered with the system. 
 * Encapsulates 'users' row from database.
 * 
 * Table Structure: 'mission_config'
 * - user_id           (string)   Unique ID given to the user. 
 * - username          (string)   Username associated with the account
 * - password          (string)   Encrypted password
 * - session_id        (string)   Session ID to track logged in users
 * - is_admin          (bool)     True if the user has administrative priviledges
 * - is_crew           (bool)     True if the user is a crew member (in the habitat)
 * - last_login        (datetime) Date/time of last logging
 * - is_password_reset (bool)     True to force password reset on next login
 * - preferences       (string)   JSON string to store user preferences
 * 
 * Additional Fields:
 * - conversations     (int[])    Array of conversation ids the user belongs to
 * 
 * Implementation Notes:
 */
class User
{
    /**
     * Data from 'mission_config' database table. 
     * @access private
     * @var array
     */
    private $data;

    /**
     * Session id for current logged in user.
     * @access private
     * @var string
     */
    private $session_id;

    /**
     * Struct to manage user preferences
     * @access private
     * @var array
     */
    private $preferenceArray;

    /**
     * User constructor. 
     * 
     * Appends object data with the field conversations (array) containing
     * an array of conversation ids that the user belongs to. 
     * 
     * @param array $data Row from 'msg_files' database table. 
     */
    public function __construct($data)
    {
        $this->data = $data;

        // Explode list of conversations the user belongs to. 
        if(isset($data['conversations']))
        {
            $this->data['conversations'] = explode(',', $this->data['conversations']);
        }
        else
        {
            $this->data['conversations'] = array();
        }

        // Parse user preferences
        if(isset($data['preferences']))
        {
            $this->preferenceArray = json_decode($this->data['preferences']);
        }
        else
        {
            $this->preferenceArray = array();
        }
    }

    /**
     * Accessor for User fields. Returns value stored in the field $name 
     * or null if the field does not exist. 
     * 
     * @param string $name Name of field being requested. 
     * @return mixed Value contained by the field requested. 
     */
    public function __get($name)
    {
        $result = null;

        if(array_key_exists($name, $this->data)) 
        {
            // If the field starts with 'is_', then treat it as a bool
            // where 1=TRUE and 0=FALSE. 
            $pos = strpos($name, 'is_');
            if($pos !== false && $pos == 0)
            {
                $result = ($this->data[$name] == 1);
            }
            else
            {
                $result = $this->data[$name];
            }
        }
        else
        {
            Logger::warning('User __get("'.$name.'")', $this->data);
        }

        return $result;
    }

    /**
     * Returns a string describing the planet/celestial body where
     * the user resides (e.g., Earth for MCC or Mars for the Habitat).
     * 
     * @return string Planet/celestial body where user resides.
     */
    public function getLocation(): string
    {
        // Read config for planet names.
        $mission = MissionConfig::getInstance();
        // Return planet name based on user's location. 
        return ($this->is_crew) ? $mission->hab_planet : $mission->mcc_planet;
    }

    /**
     * Returns true if the given password matches the one in the database.
     * 
     * @param string $password Password entered by the user at login. 
     * @return bool True if the password matches what's stored in the database.
     */
    public function isValidPassword(string $password): bool
    {
        return (User::encryptPassword($password) == $this->password);
    }

    /**
     * Encrypt a password.
     *
     * @param string $password
     * @return string Encrypted password
     */
    public static function encryptPassword(string $password) : string
    {
        // Select the appropriate hash funciton for your application. 
        return hash('sha256', $password);
    }

    /**
     * Generates and returns a unique session ID to use. 
     * 
     * @return string Session ID.
     */
    public function createNewSession() : string
    {
        $this->session_id = dechex(rand(0,time())).
            dechex(rand(0,time())).dechex(rand(0,time()));
        return $this->session_id;
    }

    /**
     * Get string representation of last login in the MCC timezone.
     * If the user never logged in, the string will be blank.
     *
     * @return string
     */
    public function getLastLogin() : string 
    {
        $lastLogin = '';
        if($this->last_login != null)
        {
            $mission = MissionConfig::getInstance();
            $lastLogin = DelayTime::convertTimestampTimezone(
                $this->last_login, 'UTC', $mission->mcc_timezone);
        }
        return $lastLogin;
    }

    /**
     * Read the value of a user preference. 
     *
     * @param string $name
     * @return mixed Return null if $name not found. 
     */
    public function readUserPreference(string $name)
    {
        $ret = null;

        if(isset($this->preferenceArray[$name]))
        {
            $ret = $this->preferenceArray[$name];
        }

        return $ret;
    }

    /**
     * Update hte value of a user preference.
     *
     * @param string $name
     * @param string $value
     * @param boolean $updateNow - If false, value is not updated in DB. A subsequent call is needed. 
     */
    public function updateUserPreference(string $name, string $value, bool $updateNow=true) 
    {
        $this->preferenceArray[$name] = $value;
        if($updateNow)
        {
            $this->saveUserPreference();
        }
    }

    /**
     * Save user preferences to DB. 
     *
     * @return void
     */
    public function saveUserPreference()
    {
        $userDao = UsersDao::getInstance();
        $userDao->update(array('preference' => json_encode($this->preferenceArray)), $this->user_id);
    }
}

?>