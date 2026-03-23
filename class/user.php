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
<<<<<<< Updated upstream
        // Select the appropriate hash funciton for your application. 
        return hash('sha256', $password);
=======
        // Use Argon2id for secure password hashing
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,    // 64MB memory cost
            'time_cost' => 4,          // 4 iterations
            'threads' => 3             // 3 parallel threads
        ]);
    }

    /**
     * Validate password complexity requirements.
     *
     * @param string $password Password to validate
     * @param string $username Username for additional checks
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validatePasswordComplexity(string $password, string $username = '') : array
    {
        $errors = [];
        $valid = true;

        // Minimum length
        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long';
            $valid = false;
        }

        // Character requirements
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
            $valid = false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
            $valid = false;
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
            $valid = false;
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
            $valid = false;
        }

        // Check for excessive consecutive characters
        if (preg_match('/(.)\1{3,}/', $password)) {
            $errors[] = 'Password cannot contain more than 3 consecutive identical characters';
            $valid = false;
        }

        // Check for sequential characters (like 123, abc)
        if (preg_match('/(012|123|234|345|456|567|678|789|890)/', $password) ||
            preg_match('/(abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i', $password)) {
            $errors[] = 'Password cannot contain sequential characters';
            $valid = false;
        }

        // Check if password contains username
        if (!empty($username) && stripos($password, $username) !== false) {
            $errors[] = 'Password cannot contain your username';
            $valid = false;
        }

        // Check against common passwords (basic check)
        $commonPasswords = ['password', '123456', 'qwerty', 'admin', 'letmein', 'welcome', 'monkey', 'dragon', 'password1'];
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = 'Password is too common, please choose a more unique password';
            $valid = false;
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Check if the account is currently locked out.
     *
     * @return bool True if account is locked
     */
    public function isAccountLocked(): bool
    {
        if (!isset($this->data['lockout_until']) || $this->data['lockout_until'] === null) {
            return false;
        }

        $now = new DateTime();
        $lockoutTime = new DateTime($this->data['lockout_until']);

        return $now < $lockoutTime;
    }

    /**
     * Get the remaining lockout time in seconds.
     *
     * @return int Seconds remaining, or 0 if not locked
     */
    public function getLockoutRemainingSeconds(): int
    {
        if (!$this->isAccountLocked()) {
            return 0;
        }

        $now = new DateTime();
        $lockoutTime = new DateTime($this->data['lockout_until']);

        return $lockoutTime->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Record a failed login attempt and potentially lock the account.
     *
     * @return bool True if account should be locked
     */
    public function recordFailedLogin(): bool
    {
        $currentAttempts = isset($this->data['failed_attempts']) ? (int)$this->data['failed_attempts'] : 0;
        $this->data['failed_attempts'] = $currentAttempts + 1;
        $this->data['last_failed_attempt'] = date('Y-m-d H:i:s.v');

        // Lockout thresholds: 5 attempts = 5min, 10 attempts = 30min, 15+ attempts = 2hr
        $lockoutTimes = [5 => 300, 10 => 1800, 15 => 7200]; // seconds

        foreach ($lockoutTimes as $attempts => $lockoutSeconds) {
            if ($this->data['failed_attempts'] >= $attempts) {
                $this->data['lockout_until'] = date('Y-m-d H:i:s.v', strtotime("+{$lockoutSeconds} seconds"));
                return true;
            }
        }

        return false;
    }

    /**
     * Clear failed login attempts (on successful login).
     */
    public function clearFailedLoginAttempts(): void
    {
        $this->data['failed_attempts'] = 0;
        $this->data['lockout_until'] = null;
        $this->data['last_failed_attempt'] = null;
    }

    /**
     * Manually unlock an account (admin function).
     */
    public function unlockAccount(): void
    {
        $this->clearFailedLoginAttempts();
>>>>>>> Stashed changes
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
