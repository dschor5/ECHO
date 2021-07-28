<?php

class DatabaseException extends DatabaseException
{
    public function __construct(string $query, string $error, array $backtrace)
    {
        $message = "QUERY: {$query}\n\n". 
                   "ERROR: {$error}\n\n".
                   "BACKTRACE:\n";
        foreach($backtrace as $call)
        {
            $message .= $call."\n";
        }
        parent::__construct($message);
    }
}

?>