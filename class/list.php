<?php

class ListGenerator
{
    private $headings;
    private $rows;
    const TAB = "    ";
    
    public function __construct(array $headings)
    {
        $this->headings = $headings;
        $this->rows = array();
    }

    public function addRow(array $row)
    {
        if(count($this->rows) % 2 == 1)
        {
            $content = self::TAB.self::TAB.'<tr class="list-row">'.PHP_EOL;
        }
        else
        {
            $content = self::TAB.self::TAB.'<tr class="list-row darker">'.PHP_EOL;
        }

        foreach($this->headings as $name => $value)
        {
            $data = (isset($row[$name])) ? $row[$name] : '';
            $content .= self::TAB.self::TAB.self::TAB.'<td class="list-row">'.$data.'</td>'.PHP_EOL;
        }

        $this->rows[] = $content.self::TAB.self::TAB.'</tr>'.PHP_EOL;
    }

    public function build()
    {
        $content = '<table class="list">'.PHP_EOL;
        $content .= self::TAB.self::TAB.'<tr>'.PHP_EOL;
        foreach($this->headings as $heading)
        {
            $content .= self::TAB.self::TAB.self::TAB.'<th>'.$heading.'</th>'.PHP_EOL;
        }
        $content .= self::TAB.self::TAB.'</tr>'.PHP_EOL;
        foreach($this->rows as $row)
        {
            $content .= $row;
        }
        $content .= self::TAB.'</table>'.PHP_EOL;
        return $content;
    }
}


?>
