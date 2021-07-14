<?php

require_once('index.php');
require_once('modules/default.php');

class HomeModule extends DefaultModule
{
    public function compile()
    {
        $content = '';

        $subaction = (isset($_GET['subaction'])) ? $_GET['subaction'] : 'home';

        switch($subaction)
            {
            default:
                $content .= 'HOME PAGE!!!';
                break;
            }

         return Main::loadTemplate('modules/home.txt', array(), array());

    }
}

?>