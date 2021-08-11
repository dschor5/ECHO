<?php

class DatabaseException extends Exception
{
    public function __construct(string $query, string $error)
    {
        $trace = parent::getTraceAsString();
        $message = "QUERY: {$query}".PHP_EOL.PHP_EOL. 
                   "ERROR: {$error}".PHP_EOL.PHP_EOL.
                   "BACKTRACE: {$trace}".PHP_EOL.PHP_EOL;
        parent::__construct($message);
    }
}

?>