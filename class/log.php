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
    const ERROR     = 0;
    const ERROR_STR = 'ERROR';

    /**
     * Level Threshold: WARNING - Applicaiton can continue.
     */
    const WARNING     = 1;
    const WARNING_STR = 'WARNING';

    /**
     * Level Threshold: DEBUG - Informaiton for developer only.
     */
    const DEBUG     = 2;
    const DEBUG_STR = 'INFO';

    /**
     * Constant date format used for logging errors.
     */
    const DATE_FORMAT = 'Y-m-dTH:i:s';

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
        self::logMessage(self::ERROR_STR, $message, $context);
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
        self::logMessage(self::WARNING_STR, $message, $context);
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
        self::logMessage(self::DEBUG_STR, $message, $context);
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

    /**
     * Returns last X lines from the log into an array to display on GUI.
     * @param int $lines Number of lines to read from log. 
     * @return array
     */
    public static function tailLog($lines = 20) : string
    {
        global $config;
        global $server;

        $output = '';

        $filename = $server['host_address'].$config['logs_dir'].'/'.$config['log_file'];
        $text = Logger::tailCustom($filename, $lines);
        $lines = explode(PHP_EOL, $text);
        foreach($lines as $line)
        {
            [$logTime, $logType, $logText] = explode(" ", $line, 3);
            $logType = substr($logType, 1, -1);

            $output .= Main::loadTemplate('admin-data-log.txt', array(
                '/%log-time%/' => $logTime, 
                '/%log-type%/' => strtolower($logType), 
                '/%LOG-TYPE%/' => strtoupper($logType), 
                '/%log-text%/' => $logText
            ));
        }

        return $output;
    }

    /**
	 * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     * Modified by Dario Schor to work with different EOL configurations.
	 * @author Torleif Berger, Lorenzo Stanco
	 * @link http://stackoverflow.com/a/15025877/995958
	 * @license http://creativecommons.org/licenses/by/3.0/
	 */
	private static function tailCustom($filepath, $lines = 1, $adaptive = true) 
    {
		// Open file
		$f = @fopen($filepath, "rb");
		if ($f === false)
        {
            return false;
        }

		// Sets buffer size, according to the number of lines to retrieve.
		// This gives a performance boost when reading a few lines from the file.
		if (!$adaptive)
        {
            $buffer = 4096;
        }
		else
        {
            $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
        }

		// Jump to last character
		fseek($f, -1, SEEK_END);

		// Read it and adjust line number if necessary
		// (Otherwise the result would be wrong if file doesn't end with a blank line)
		if (fread($f, 1) != PHP_EOL)
        {
            $lines -= 1;
        }
		
		// Start reading
		$output = '';
		$chunk = '';

		// While we would like more
		while (ftell($f) > 0 && $lines >= 0) 
        {
        	// Figure out how far back we should jump
			$seek = min(ftell($f), $buffer);

			// Do the jump (backwards, relative to where we are)
			fseek($f, -$seek, SEEK_CUR);

			// Read a chunk and prepend it to our output
			$output = ($chunk = fread($f, $seek)) . $output;

			// Jump back to where we started reading
			fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

			// Decrease our line counter
			$lines -= substr_count($chunk, PHP_EOL);
		}

		// While we have too many lines
		// (Because of buffer size we might have read too many)
		while ($lines++ < 0) 
        {
			// Find first newline and remove all text before that
			$output = substr($output, strpos($output, PHP_EOL) + 1);
		}

		// Close file and return
		fclose($f);
		return trim($output);
	}
}

?>