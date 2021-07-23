<?php

require_once('config.inc.php');

/*
   CLASS: Database
   PURPOSE: This is the main object in the database
           layer.  It runs all queries and manages all
           database abstraction objects (dao's)

   NOTE: If you would like this script to run with a different
   database structure, all you should need to rewrite is this file
   the dao.php and the result.php files.

*/
class Database
{
    private $db = null;            // private: this is the mysql database reference.
    private $daos = array();    // private: an array of already constructed dao's
    private $error = '';        // private: if theres an error, it will be in here.
    private $errorQuery = '';    // private: if theres an error, the original query will be in here.
    private $errorTrace = '';    // private: if theres an error, the debug trace will be in here.
    public $query_count = 0;    // public: keeps track of the number of queries
    public $query_time = 0;    // public: keeps track of the total time taken.

    private static $instance = null;

    /*
       PUBLIC: Database
       PURPOSE: Constructor
       @param $user
       @param $pass
       @param $dbname
       @param $hostname
       @return Database object
     */
    private function __construct(string $user, string $pass, string $dbname, string $hostname)
    {
        $this->db = new mysqli($hostname, $user, $pass, $dbname);
        if(mysqli_connect_errno())
        {
            echo "No Connection ".mysqli_connect_error();
        }
    }

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            global $database;
            self::$instance = new Database(
                $database['db_user'],
                $database['db_pass'],
                $database['db_name'],
                $database['db_host']);
        }

        return self::$instance;
    }

    /*
       PUBLIC getErr
       PURPOSE: Gets the last error message to occur
       @return string $error
    */
    public function getErr()
    {
        return ($this->error == '')? false : array('query'=>$this->errorQuery,'error'=>$this->error,'trace'=>$this->errorTrace);
    }


    /*
       PUBLIC: getDao
       PURPOSE: Gets the database abstraction object
               corresponding to the table specified.
               Typically each table has its own dao which
               implements specific functions for that table.

       @return dao object resource
     */
    public function &getDao(string $dao) : Dao
    {
        global $config;
        $false=false;

        // check to see if the dao has already been
        // created, and if not, create it.
        if (!isset($this->daos[$dao]))
        {
            if (!in_array($dao,$config['db_tables']))
                return $false;

            require_once strtolower($dao)."Dao.php";

            //make sure that the dao specified actually exists,
            //and instantiate it
            if (class_exists($dao.'Dao'))
            {
                $className=$dao.'Dao';
                $this->daos[$dao] = new $className($this);
            }
            else
            {
                $this->error = 'Invalid DAO Requested';
                return $false;
            }
        }

        //spit the dao back to the user.
        return $this->daos[$dao];
    }


    /* PRIVATE: query
           PURPOSE: Runs an actual query and (if needed) creates
                   a result object to return.

           NOTE:    This function is not really private, but it is
                   meant to be accessed ONLY by the Dao's.  If you
                   use this function outside of a dao, it defeats
                   the purpose of having a database abstraction layer.

           @param:  string $query   - the actual query to run
           @param:  int $keepResult - an optional flag.  If set,
                             a result object will be returned
           @return: boolean/Result (depending on flag above)
     */
    public function query(string $query, bool $keepResult = true) 
    {
        //get the start time
        $time_start = $this->getmicrotime();

        //run the query
        $result = $this->db->query($query);

        //get the end time.
        $time_end = $this->getmicrotime();

        //add the query time to the total query time
        //being stored and increment the query count.
        $this->query_time+= $time_end - $time_start;
        $this->query_count++;

        //if there was an error store the error
        //and return false.
        if ($this->db->error != '')
        {
            $this->errorQuery = $query;
            $this->error =  $this->db->error;
            $this->errorTrace = debug_backtrace();
            echo '<font color=red><b>Database Error.</b></font><br>';
            //echo $this->errorQuery;
            //echo $this->error;
            //echo $this->errorTrace;
            return null;
        }

        //if we need to keep the result, create
        //a result object and return it.
        return $result;
    }


    /* PUBLIC:  prepareStatement
       PURPOSE: This function is meant to prepare a string for use in
                a database query.  It is very important to use this to
                clean any data before issuing a query in order to prevent
                an end user from constructing malicious input designed to
                mess up the database.

       @param:  string value
       @return: string cleanvalue
     */
    public function prepareStatement(string $value): string
    {
        return $this->db->real_escape_string(stripslashes($value));
    }


    /* PRIVATE: getmicrotime
       PURPOSE: Gets the current time in microsecconds since epoch
       @return: float microseconds
     */
    public function getMicrotime()
    {
        $time=microtime();
        return substr($time,11).substr($time,1,9);
    }


    /* PRIVATE: insert_id
       PURPOSE: Gets the last ID inserted.  Used when a table has auto_increment.

       NOTE:    Once again, this is not really a private function, but it should
               NOT be accessed outside of a DAO.

       @return: int lastInsertedId
    */
    public function getLastInsertId()
    {
        return $this->db->insert_id;
    }
}
?>
