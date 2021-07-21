<?php
/*
   CLASS:   Result
   PURPOSE: Manages the results of database queries
 */
class Result
{
    private $result    = null; // private: mysql result resource
    private $rowCount = null; // private: row count

    /* Public: Result
       Purpose: Constructor
       @param: mysql result resource
       @return: result object resource
     */
    public function __construct(&$result)
    {
        $this->result = new mysqli_result($result);
    }


    /* PUBLIC: get_row
       PURPOSE: gets the next row from the result.  If there are no more
               rows, it frees the mysql result.
       @return: string[] - an Associative array of key value pairs for the entry.
     */
    public function getRow ()
    {
        if ($this->result == null) 
        {
            return false;
        }

        if (($row=$this->result->fetch_array(MYSQLI_ASSOC)) !== false)
            return $row;
        else
        {
            $this->result->free_result();
            return false;
        }
    }


    /* PUBLIC: row_count()
       PURPOSE: gets the row count (if it hasnt got it yet)
               and returns it.
       @return: int row_count
     */
    public function getRowCount ()
    {
        return $this->result->num_rows;
    }
}

?>
