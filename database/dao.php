<?php

require_once("database/database.php");

/**
 * Abstract base class containing common properties/functionality for all 
 * Database Abstraction Objects (DAO). 
 */
abstract class Dao
{

    /**
     * Reference to database connection. 
     * @access protected
     * @var Database
     */
    protected $database; 

    /**
     * Name of table represented by this DAO.
     * @access private
     * @var string
     */
    private $name;

    /**
     * Name of primary id for the table.
     * @access private
     * @var string
     */
    private $id;

    /**
     * Search for any of these keyrods. 
     * @access private
     * @var int
     */
    const SEARCH_ANY = 0;

    /**
     * Search for all of these keywords. 
     * @access private
     * @var int
     */
    const SEARCH_ALL = 1;

    /**
     * Search for exact phrase. 
     * @access private
     * @var int
     */
    const SEARCH_PHRASE = 2;

    /**
     * Constructor. Initializes the name of the database table and the primary id. 
     * 
     * @param string $name Name of database table represented by this DAO. 
     * @param string|null $id Field name used as primary id. 
     */
    protected function __construct(string $name, ?string $id=null)
    {
        $this->database = Database::getInstance();
        $this->name     = $name;
        $this->id       = $id;
    }

    /**
     * Start/End transaction methods used to group transactions as an atomic operation. 
     */
    public function startTransaction()
    {
        $this->database->query('START TRANSACTION',0);
    }

    /**
     * Start/End transaction methods used to group transactions as an atomic operation. 
     * 
     * @param boolean $commit True to commit operation. False to rollback changes.
     */
    public function endTransaction($commit=true)
    {
        $this->database->query(($commit) ? 'COMMIT;' : 'ROLLBACK;');
    }

    /** 
     * Drop a field from the table. 
     * 
     * @param string|int If an int is provided, then treat it as the unique id
     *                   to drop. Otherwise, assume it is the WHERE clause.
     *                   Default to '*' which would drop all rows.
     * @return bool True on success 
     */
    public function drop($id = '*') : bool
    {
        // Build query for this table. 
        $query = "delete from `{$this->name}`";

        // If the id (int) is provided, then delete that row only. 
        if (intval($id) > 0)
        {
            $query .= " where `{$this->id}` = '$id'";
        }
        // Otherwise, assume it is a WHERE clause to apply in the operation. 
        else if ($id != '*')
        {
            $query .= " where ".$id;
        }

        return $this->database->query($query, false);
    }

    /**
     * Select / query this database table. 
     * 
     * @param string $what Fields to select from this table. Default to '*' for all. 
     * @param string $where Where clause to select rows from the table. 
     *                      If an int is provided, then treat it as the unique id
     *                      to drop. Otherwise, assume it is the WHERE clause.
     *                      Default to '*' which would select all rows.
     * @param string|array $sort  Name of field(s) to sort results by.
     * @param string|array $order 'ASC' or 'DSC' order to sort each of the $sort fields.
     * @param int $limist_start Select subset of matched rows. 
     *                      Limit start is the index of the first row to return. 
     *                      Default to null which means use database default.
     * @param int $limit_count Select number of rows to return. 
     *                      Default to null which means use database default.
     * @return array Associative array of database rows returned.                       
     */
    public function select($what = '*', $where = '*', $sort = '', $order = 'ASC', 
        $limit_start = null, $limit_count = null)
    {
        // Form query string for this table. 
        $query = "select $what from `{$this->name}`";

        // Integer where clause means select by primary id. 
        if (intval($where) > 0)
        {
            $query .= " where `{$this->id}` = '$where'";
        }
        // Otherwise, if where clause is not a wildcard, assume it is a well 
        // constructed database statement.
        elseif ($where != '*')
        {
            $query .= " where " . $where;
        }

        if (is_array($sort))
        {
            if (count($sort) > 0)
            {
                $query .= ' order by ';
            }

            for ($i=0;$i<count($sort);$i++)
            {
                $query .= '`'.$sort[$i].'` ';
                $query .= (is_array($order))?$order[$i]:$order;
                if ($i < count($sort)-1)
                {
                    $query .= ', ';
                }
            }

        } 
        elseif ($sort != '')
        {
            $query .= " order by `{$sort}` $order";
        }

        if ($limit_start >= 0 && $limit_count > 0)
        {
            $query .= " LIMIT $limit_start,$limit_count";
        }

        //add the allmighty semicolon to the end
        $query .= ';';

        //run it!
        
        
        return $this->database->query($query);
    }


    /* PUBLIC:  insert
    PURPOSE: Inserts into the current table.
    @param:  string[] - an array of strings corresponding to each field in the table.
    @return  int or boolean - If successfully, returns the ID of the new entry, otherwise
        false.
    */
    public function insert($fields)
    {
        $query = "insert into `{$this->name}` (";

        $keys = array();
        $values = array();
        foreach ($fields as $key => $value)
        {
            $keys[] = '`'.$key.'`';
            if ($value === null)
                $values[] = 'NULL';
            else
                $values[] = '"'.$this->database->prepareStatement($value).'"';
        }

        $query .= join(',',$keys).') values ('.join(',',$values).');';
        if ($this->database->query($query,0))
        {
            // get the insert ID.
            $id = $this->database->getLastInsertId();
            return $id;

        } 
        return false;
    }

    public function insertMultiple($entries)
    {
        $valuesStr = array();
        
        if(count($entries) == 0)
        {
            return true;
        }
        
        foreach($entries as $fields)
        {
            $values = array();
            $keys = array();
            foreach ($fields as $key => $value)
            {
                $keys[] = '`'.$key.'`';
                if ($value === null)
                    $values[] = 'NULL';
                else
                    $values[] = '"'.$this->database->prepareStatement($value).'"';
            }
            $keysStr = join(',', $keys);
            $valuesStr[] = '('.join(',', $values).')';
        }

        $query = 'INSERT INTO `'.$this->name.'` '.
                    '('.$keysStr.') VALUES '.join(',', $valuesStr).';';

        if ($this->database->query($query, false))
        {
            return $this->database->getNumRowsAffected();
        } 
        return false;
    }


    /* PUBLIC:  replace
    PURPOSE: Replaces into the current table.
    @param:  string[] - an array of strings corresponding to each field in the table.
    @return  int or boolean - If successfully, returns the ID of the new entry, otherwise
        false.
    */
    public function replace($fields)
    {
        $query = "replace into `{$this->name}` values(";

        $values = array();
        foreach ($fields as $value)
            $values[] = '"'.$this->database->prepareStatement($value).'"';

        $query .= join(',',$values) . ');';

        if ($this->database->query($query,0))
        {
            // get the insert ID.
            $id = $this->database->insert_id();
            return $id;

        } else
            return false;
    }


    /* PUBLIC: update
    PURPOSE: Updates the entries in a table as specified.
    @param  string[] - An associative array whose keys are the names
              of the fields to change and the values are the new
              values to use.

    @param  string   - The where clause (* for all) or specific ID to use
    @return boolean
    */
    public function update($fields,$where = '*')
    {

        $query = "update `{$this->name}` set ";

        $tmp = array();
        foreach ($fields as $key=>$value)
            $tmp[]= ($value === null)?
                "`$key`=null":
                "`$key`='".$this->database->prepareStatement($value)."'";

        $query .= join(', ',$tmp);

        if (intval($where) > 0)
            $query .= " where `{$this->id}` = '$where'";
        else if ($where != '*')
            $query .= " where " . $where;

        $query.=';';

        return $this->database->query($query, false);
    }


    /* PUBLIC searchClause
       PURPOSE: Generates a where clause used for searching.

       method == self::SEARCH_ANY
       method == self::SEARCH_ALL
       method == self::SEARCH_PHRASE

       @param String[]  columns
       @param String searchString
       @param int method
       @result String whereClause
     */
    protected function searchClause(array $search, string $keywords, int $method=0)
    {
        if (count($search) > 0)
        {
            $keywords=trim($keywords);
            $search_terms = array();

            $keywords = ($method == self::SEARCH_PHRASE ? array($this->database->prepareStatement($keywords)):preg_split("/\s+/",$keywords));
            foreach ($keywords as $key)
            {
                if (strlen($key) >= 3)
                {
                    $tmp = array();
                    foreach ($search as $col)
                        if ($method == self::SEARCH_ALL)
                            $tmp[] = $col . " like '%" .$this->database->prepareStatement($key). "%'";
                        else
                            $search_terms[] = $col . " like '%" .$this->database->prepareStatement($key). "%'";

                    if ($method == self::SEARCH_ALL)
                        $search_terms[] = '('.join(' or ',$tmp).')';
                }
            }
            return (count($search_terms) > 0)? join(($method == self::SEARCH_ALL)? " and ": " or ",$search_terms) : '*';
        }
        return '*';
    }
}

?>
