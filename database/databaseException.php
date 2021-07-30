<?php

class DatabaseException extends Exception
{
    public function __construct(string $query, string $error)
    {
        $trace = parent::getTraceAsString();
        $message = "QUERY: {$query}\n\n". 
                   "ERROR: {$error}\n\n".
                   "BACKTRACE: {$trace}\n\n";
        parent::__construct($message);
    }
}

?>