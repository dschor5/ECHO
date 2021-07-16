<?php

require_once('index.php');
require_once('modules/default.php');

class HomeModule extends DefaultModule
{
    public function compile()
    {
        $content = '';

        if(isset($_GET['subaction']) && $_GET['subaction'] == 'login')
        {
            $this->login();
        }

        $this->addCss('login');
        $this->addJavascript('jquery-3.6.0.min');
        $this->addJavascript('login');
        
        


        $subaction = (isset($_GET['subaction'])) ? $_GET['subaction'] : 'home';

        switch($subaction)
            {
            default:
                $content .= 'HOME PAGE!!!';
                break;
            }

         return Main::loadTemplate('modules/home.txt', array(), array());

    }

    private function login()
    {
        global $config;
        $message = '';

        if(isset($_POST['uname']) && isset($_POST['upass']))
        {
            $usersDao = UsersDao::getInstance();
            $user = $usersDao->getByUsername($_POST['uname']);

            if($user != null && $user->isValidPassword($_POST['upass']))
            {
                $this->user = $user;
                $sessionId = $user->createNewSession();
                $this->main->setCookie(array('sessionId'=>$sessionId,'username'=>$_POST['uname']));
                //header('Location: http://'.$config['site_url'].'/chat');
                header('HTTP/1.1 200 OK');
                exit();
            }
            else
            {
                header('HTTP/1.1 201 Created');
                exit();
            }
        }
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