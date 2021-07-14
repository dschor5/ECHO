<?php

class ErrorModule extends DefaultModule
{
    public function getPageTitle()
    {
        return parent::getpageTitle();
    }

    public function getNavigation()
    {
        return '';
    }

    public function compile()
     {
         return $this->main->loadTemplate('modules/error.txt', array(),array());
     }
}

?>