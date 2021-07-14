<?php

class AjaxModule extends DefaultModule
{
     public function compile()
     {
        global $config;
        $xml='';

        $subaction = (isset($_POST['subaction'])) ? strtolower(trim($_POST['subaction'])) : '';

        
        $xml = '<response>'.$xml.'</response>';

        //dump some headers
        header('Pragma: no-cache');
        header('Content-length: '.strlen($xml));
        header('Content-Type: application/xml');

        //output the xml and exit.
        echo $xml;
        exit();
     }
}
?>