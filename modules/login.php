<?php

require_once('modules/default.php');
require_once('class/user.php');
require_once('database/usersDao.php');

class LoginModule extends DefaultModule
{
    public function getPageTitle()
    {
        return parent::getpageTitle().': LOGIN';
    }

    public function compile()
     {
        global $config;
        $content = '';

        $this->addCss('login');

        $subaction = null;
        if(isset($_GET['subaction']))
            $subaction = $_GET['subaction']; 

        if($this->user != null)
        {
            if($subaction == 'logout')
            {
                $content .= $this->logout();
            }
            elseif($this->user->isResetPasswordRequired())
            {
                $content .= $this->forceReset();
            }
            else
            {
                header('Location: http://'.$config['site_url'].'/error');
            }
        }
        else
        {
            $content .= $this->login();
        }

         return $content;
     }


    private function logout() : string
    {
        global $config;

        $this->user = null;
        $this->main->setCookie(array('sessionId'=>null,'username'=>null));
        header('Location: http://'.$config['site_url']);
        return 'Logging out, please wait while you are redirected to the homepage.';
    }

    private function forceReset() : string
    {
        global $config;
        $content = '';

        $usersDao = UsersDao::getInstance();
        $user = $this->user;
        $message = '';

        if(isset($_POST['oldpass']) && isset($_POST['newpass']) && isset($_POST['newpass2']))
        {
            $oldpass =  md5(trim($_POST['oldpass']));
            $newpass =  md5(trim($_POST['newpass']));
            $newpass2 = md5(trim($_POST['newpass2']));

            if($newpass == $newpass2 && strlen(trim($_POST['newpass'])) > 5 &&        //new passwords are equal and at least 5 chars
               $oldpass == $user->data['password'] &&        //old password matches database
               $oldpass != $newpass)                        //new password cannot be the same as the old password
            {
                $usersDao->update(array('password'=>$newpass, 'password_reset'=>'0'), $user->data['id']);
                header('Location: http://'.$config['site_url'].'/production/');
            }
            else
            {
                $message = 'Could not complete your task. Try again.';
            }
        }

        return $this->main->loadTemplate('modules/login-reset.txt', array(), array($form->compile()));
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
}
