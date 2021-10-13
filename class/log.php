<?php

/**
 * Logger class to track errors, warnings, and debugging information. 
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
     * Log ERROR level message. 
     *
     * @param [type] $message
     * @param [type] $context
     * @return void
     */
    public static function error($message, $context=null)
    {
        self::logMessage('ERROR', $message, $context);
    }

    public static function warning($message, $context=null)
    {
        if(self::$levelThreshold < Logger::WARNING)
        {
            return;
        }
        self::logMessage('WARNING', $message, $context);
    }

    public static function debug(string $message, ?array $context=null)
    {
        if(self::$levelThreshold < Logger::DEBUG)
        {
            return;
        }
        self::logMessage('DEBUG', $message, $context);
    }

    private static function logMessage($type, $message, $context)
    {
        global $config;
        global $server;
        
        $logEntry = date(self::DATE_FORMAT).' ['.$type.'] '.$message;
        if($context != null)
        {
            $logEntry .= ' '.json_encode($context);
        }

        $folder = $server['host_address'].$config['logs_dir'];
        if(is_writeable($folder))
        {
            error_log($logEntry.PHP_EOL, self::ERROR_LOG_DEST, $folder.'/'.$config['log_file']);
        }
    }
}

?>