<?php

class ListGenerator
{
    private $headings;
    private $rows;
    
    public function __construct(array $headings)
    {
        $this->headings = $headings;
        $this->rows = array();
    }

    public function addRow(array $row)
    {
        $content = '<tr>';

        foreach($this->headings as $name => $value)
        {
            $data = (isset($row[$name])) ? $row[$name] : '';
            $content .= '<td>'.$data.'</td>';
        }

        $this->rows[] = $content.'</tr>';
    }

    public function build()
    {
        $content = '<table><tr><th>'.join('</th><th>', $this->headings).'</th></tr>';
        $content .= '<tr><td>'.join('</td><td>', $this->rows).'</td></tr></table>';
        return $content;
    }
}


?>
