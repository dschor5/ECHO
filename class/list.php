<?php

class ListGenerator
{
    private $headings;
    private $rows;
    const EOL = "\n";
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
            $content = self::TAB.self::TAB.'<tr class="list-row">'.self::EOL;
        }
        else
        {
            $content = self::TAB.self::TAB.'<tr class="list-row darker">'.self::EOL;
        }

        foreach($this->headings as $name => $value)
        {
            $data = (isset($row[$name])) ? $row[$name] : '';
            $content .= self::TAB.self::TAB.self::TAB.'<td class="list-row">'.$data.'</td>'.self::EOL;
        }

        $this->rows[] = $content.self::TAB.self::TAB.'</tr>'.self::EOL;
    }

    public function build()
    {
        $content = '<table class="list">'.self::EOL;
        $content .= self::TAB.self::TAB.'<tr>'.self::EOL;
        foreach($this->headings as $heading)
        {
            $content .= self::TAB.self::TAB.self::TAB.'<th>'.$heading.'</th>'.self::EOL;
        }
        $content .= self::TAB.self::TAB.'</tr>'.self::EOL;
        foreach($this->rows as $row)
        {
            $content .= $row;
        }
        $content .= self::TAB.'</table>'.self::EOL;
        return $content;
    }
}


?>
