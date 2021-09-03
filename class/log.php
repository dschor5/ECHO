<?php

class Logger
{
    const ERROR    = 0;
    const WARNING  = 1;
    const DEBUG    = 2;

    const DATE_FORMAT = 'Y-m-d H:i:s';
    const ERROR_LOG_DEST = 3;

    private static $levelThreshold = Logger::WARNING;
    
    private function __construct() {}

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

    public static function debug($message, $context=null)
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
        
        error_log($logEntry.PHP_EOL, self::ERROR_LOG_DEST, $server['host_address'].$config['log_file']);
    }
}

?>