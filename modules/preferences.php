<?php

class PreferencesModule extends DefaultModule
{
    /**
     * Constructor. 
     *
     * @param User $user Current logged in user. Cannot be null.
     */
   public function __construct(&$user)
   {
      parent::__construct($user);
      $this->subJsonRequests = array(
         // Save Preferences
         'save_preferences' => 'savePreferences', 
      );

      $this->subHtmlRequests = array(
         // View settings
         'view'      => 'viewPreferences', 
         // Default
         'default'      => 'viewPreferences',
      );
   }

   /***********************/
   /* Preferences         */
   /***********************/
   protected function savePreferences() : array
   {

      return array();
   }

   protected function viewPreferences() : string
   {
      $this->addTemplates('settings.css', 'preferences.js');



      return Main::loadTemplate('user-preferences.txt', array(
         '/%alias%/'           => $this->user->alias,
         '/%avatar-filename%/' => $this->user->avatar,
      ));
   }


}

?>
