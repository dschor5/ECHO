<?php

/**
 * List Generator to format tables of data.
 * 
 * @link https://github.com/dschor5/ECHO
 */
class ListGenerator
{
    /**
     * Table headings. 
     * @access private
     * @var Array
     */
    private $headings;

    /**
     * Rows of data already formatted as HTML output.
     * @access private 
     * @var Array
     */
    private $rows;

    /**
     * Constant used to indent HTML with 4 spaces / tab. 
     * @access private
     * @var String
     */
    const TAB = "    ";
    
    /**
     * Constructor sets table headings and initializes table with empty rows array.
     *
     * @param array $headings Array of headings. 
     */
    public function __construct(array $headings)
    {
        $this->headings = $headings;
        $this->rows = array();
    }

    /**
     * Add a row to the table. 
     *
     * @param array $row Associative array of values to insert into the table.
     */
    public function addRow(array $row)
    {
        // Interleave darker rows in output. 
        if(count($this->rows) % 2 == 1)
        {
            $content = self::TAB.self::TAB.'<tr class="list-row">'.PHP_EOL;
        }
        else
        {
            $content = self::TAB.self::TAB.'<tr class="list-row darker">'.PHP_EOL;
        }

        // Iterate through the headings to add information to the correct column. 
        foreach($this->headings as $name => $value)
        {
            $data = (isset($row[$name])) ? $row[$name] : '';
            $content .= self::TAB.self::TAB.self::TAB.'<td class="list-row">'.$data.'</td>'.PHP_EOL;
        }

        // Add the row to the table. 
        $this->rows[] = $content.self::TAB.self::TAB.'</tr>'.PHP_EOL;
    }

    /**
     * Build the table. 
     * @return string HTML table. 
     */
    public function build() : string
    {
        // Create the table
        $content = '<table class="list">'.PHP_EOL;
        $content .= self::TAB.self::TAB.'<tr>'.PHP_EOL;

        // Add table headings
        foreach($this->headings as $heading)
        {
            $content .= self::TAB.self::TAB.self::TAB.'<th>'.$heading.'</th>'.PHP_EOL;
        }
        $content .= self::TAB.self::TAB.'</tr>'.PHP_EOL;

        if(count($this->rows) > 0)
        {
            // Add table rows
            foreach($this->rows as $row)
            {
                $content .= $row;
            }
        }
        else
        {
            $content .= self::TAB.self::TAB.'<tr><td colspan="'.count($this->headings).'">No data to display.</td></tr>'.PHP_EOL;
        }
        
        $content .= self::TAB.'</table>'.PHP_EOL;
        return $content;
    }
}


?>
