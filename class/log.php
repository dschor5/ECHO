<?php

/**
 * Logger class to track errors, warnings, and debugging information. 
 * Log entries are recorded as:
 *      YYYY-MM-DD HH:MM:SS [LOG_TYPE] Message <JSON encoded context>
 *
 * Implementation Notes:
 * - Implemented as a wrapper for the PHP error_log() function. 
 * - Level Threshold defines the lowest type of message to accept. 
 *   E.g., WARNING level will allow ERROR and WARNING messages. 
 * 
 * Assumption:
 * - Applicaition has write access to $server['host_address'].$config['logs_dir'].
 * 
 * @link https://github.com/dschor5/AnalogDelaySite
 */
class Logger
{
    /**
     * Level Threshold: ERROR - Applicaiton cannot recover.
     */
    const ERROR    = 0;

    /**
     * Level Threshold: WARNING - Applicaiton can continue.
     */
    const WARNING  = 1;

    /**
     * Level Threshold: DEBUG - Informaiton for developer only.
     */
    const DEBUG    = 2;

    /**
     * Constant date format used for logging errors.
     */
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Destination for error log as defined in 
     * https://www.php.net/manual/en/function.error-log.php.
     */
    const ERROR_LOG_DEST = 3;

    /**
     * Define log level threshold (ERROR, WARNING, or DEBUG).
     * @access private
     * @var int
     */
    private static $levelThreshold = Logger::WARNING;
    
    /**
     * Private constructor to prevent instantiating the class. 
     */
    private function __construct() 
    {
        // Do nothing. 
    }

    /**
     * Log an ERROR level message. Always recorded.
     *
     * @param string $message Message to log.
     * @param array|null $context Optional array to encode with the msg.
     */
    public static function error(string $message, ?array $context=null)
    {
        self::logMessage('ERROR', $message, $context);
    }

    /**
     * Log an WARNING level message. Recorded based on threshold setting.
     *
     * @param string $message Message to log.
     * @param array|null $context Optional array to encode with the msg.
     */    
    public static function warning(string $message, ?array $context=null)
    {
        if(self::$levelThreshold < Logger::WARNING)
        {
            return;
        }
        self::logMessage('WARNING', $message, $context);
    }

    /**
     * Log an DEBUG level message. Recorded based on threshold setting.
     *
     * @param string $message Message to log.
     * @param array|null $context Optional array to encode with the msg.
     */        
    public static function debug(string $message, ?array $context=null)
    {
        if(self::$levelThreshold < Logger::DEBUG)
        {
            return;
        }
        self::logMessage('DEBUG', $message, $context);
    }

    /**
     * Wrapper for error log. Note that it assumes it can write to the logs_dir. 
     *
     * @param string $type Type of error. 
     * @param string $message Message to log.
     * @param array|null $context Optional array to encode with the msg.
     * @global $config 
     * @global $server
     */
    private static function logMessage(string $type, string $message, ?array $context)
    {
        global $config;
        global $server;
        
        // Format log entry
        $dateUtc = new DateTime("now", new DateTimeZone('UTC'));
        $logEntry = $dateUtc->format(self::DATE_FORMAT).' ['.$type.'] '.$message;

        // If provided, JSON encode the $context array.
        if($context != null)
        {
            $logEntry .= ' '.json_encode($context);
        }

        // If the file is writeable, then log the message. 
        $folder = $server['host_address'].$config['logs_dir'];
        if(is_writeable($folder))
        {
            error_log($logEntry.PHP_EOL, self::ERROR_LOG_DEST, $folder.'/'.$config['log_file']);
        }
    }
}

?>