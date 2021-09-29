<?php

class PreferencesModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array(
            'save' => 'saveUserSettings'
        );
        $this->subHtmlRequests = array(
            'default' => 'editUserSettings'
        );
    }

    protected function saveUserSettings() : array
    {
        return array();
    }

    protected function editUserSettings() : string 
    {
        return '';
    }
}

?>