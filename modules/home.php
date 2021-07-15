<?php

require_once('index.php');
require_once('modules/default.php');

class HomeModule extends DefaultModule
{
    public function compile()
    {
        $content = '';

        $this->addCss('login');

        $subaction = (isset($_GET['subaction'])) ? $_GET['subaction'] : 'home';

        switch($subaction)
            {
            default:
                $content .= 'HOME PAGE!!!';
                break;
            }

         return Main::loadTemplate('modules/home.txt', array(), array());

    }

    private function login() : string
    {
        global $config;
        $message = '';

        if(isset($_POST['user']) && isset($_POST['pass']))
        {
            $usersDao = UsersDao::getInstance();
            $user = $usersDao->getByUsername($_POST['user']);

            if($user != null && $user->isValidPassword($_POST['pass']))
            {
                $this->user = $user;
                $sessionId = $user->createNewSession();
                $this->main->setCookie(array('sessionId'=>$sessionId,'username'=>$_POST['user']));
                header('Location: http://'.$config['site_url'].'/chat');
            }
            else
            {
            
                $message = 'ERROR';
            }
        }

        return $this->main->loadTemplate('modules/login-login.txt', array('/%message%/'=>$message));
    }

    private function logout() : string
    {
        global $config;

        $this->user = null;
        $this->main->setCookie(array('sessionId'=>null,'username'=>null));
        header('Location: http://'.$config['site_url']);
        return 'Logging out, please wait while you are redirected to the homepage.';
    }    
}

?>