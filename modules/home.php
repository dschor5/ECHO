<?php

class HomeModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array(
            'reset' => 'resetPassword',
            'login' => 'login'
        );
        $this->subHtmlRequests = array(
            'logout'     => 'logout',
            'checkLogin' => 'checkLogin',
            'default'    => 'showHomepage',
        );
    }

    public function getHeader() : string
    {
        return '';
    }

    public function compileHtml(string $subaction): string
    {
        global $server;
        if($subaction == 'logout')
        {
            $this->logout();
        }
        elseif($subaction == 'checkLogin')
        {
            if($this->user->password_reset == 1)
            {
                return $this->showResetPage();
            }
            else
            {
                header('Location: '.$server['http'].$server['site_url'].'/chat');
            }
        }

        return $this->showHomepage();
    }

    protected function checkLogin() : string 
    {
        global $server;

        if($this->user->reset_password == 0) 
        {
            header('Location: '.$server['http'].$server['site_url'].'/chat');
        }
        
        return 'RESET';
    }

    protected function showResetPage() : string
    {
        $this->addTemplates('login-reset.js', 'login.css');
        return Main::loadTemplate('home-reset.txt', array('/%username%/' => $this->user->username));
    }

    protected function showHomepage() : string
    {
        $this->addTemplates('login.js', 'login.css');
        return Main::loadTemplate('home.txt', array());
    }

    protected function resetPassword() : array
    {
        global $config;
        $response = array('success' => false);

        if(isset($_POST['password1']) && isset($_POST['password2']))
        {
            if($_POST['password1'] != $_POST['password2'])
            {
                $response['message'] = 'Passwords do not match.';
            }
            else if(strlen($_POST['password1']) < 8)
            {
                $response['message'] = 'Password must be at least 8 characters long.';
            }
            else
            {
                $uppercase = preg_match('/[A-Z]/', $_POST['password1']);
                $lowercase = preg_match('/[a-z]/', $_POST['password1']);
                $number    = preg_match('/[0-9]/', $_POST['password1']);
                $special   = preg_match('/[\W_]/', $_POST['password1']);

                if(!$uppercase || !$lowercase || !$number || !$special)
                {
                    $response['message'] = 'Password must contain at least '.
                                           'one upper case letter, '. 
                                           'one lower case letter, '. 
                                           'one number, and '. 
                                           'one special character.';
                }
                else
                {
                    $usersDao = UsersDao::getInstance();
                    if($this->user !== false)
                    {
                        $newData = array(
                            'password' => md5($_POST['password1']),
                            'password_reset' => 0
                        );
                        $usersDao->update($newData, $this->user->user_id);
                        Main::deleteCookie();
                        $response['success'] = true;
                    }
                }
            }
        }

        return $response;
    }

    protected function login() : array
    {
        global $config;
        $response = array('login' => false);

        if(isset($_POST['uname']) && isset($_POST['upass']))
        {
            $usersDao = UsersDao::getInstance();
            $user = $usersDao->getByUsername($_POST['uname']);

            if($user !== false && $user->isValidPassword($_POST['upass']))
            {
                $this->user = $user;
                $sessionId = $user->createNewSession();
                $newData = array(
                    'session_id' => $sessionId,
                    'last_login' => date('Y-m-d H:i:s', time())
                );
        
                if ($usersDao->update($newData, $this->user->user_id) !== false)
                {
                    Main::setSiteCookie(array('sessionId'=>$sessionId,'username'=>$_POST['uname']));
                    $response['login'] = true;
                }
            }
        }

        return $response;
    }

    protected function logout() : string
    {
        global $server;

        $this->user = null;
        Main::deleteCookie();
        header('Location: '.$server['http'].$server['site_url']);
        return 'Logging out, please wait while you are redirected to the homepage.';
    }    
}

?>