<?php

require_once('modules/default.php');

class HomeModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('login');
        $this->subHtmlRequests = array('logout');
    }

    public function compileJson(string $subaction): array
    {
        $response = array();

        if($subaction == 'login')
        {
            $response = $this->login();
        }

        return $response;
    }

    public function getHeader(): string
    {
        return '';
    }

    public function compileHtml(string $subaction) : string
    {
        $this->addCss('common');
        $this->addCss('login');
        $this->addJavascript('jquery-3.6.0.min');
        $this->addJavascript('login');

        if($subaction == 'logout')
        {
            $this->logout();
        }

        return Main::loadTemplate('home.txt', array());
    }

    private function login() : array
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
        
                if ($usersDao->update($newData, $this->user->getId()) !== false)
                {
                    Main::setSiteCookie(array('sessionId'=>$sessionId,'username'=>$_POST['uname']));
                    $response['login'] = true;
                }
            }
        }

        return $response;
    }

    private function logout() : string
    {
        global $server;

        $this->user = null;
        Main::deleteCookie();
        header('Location: '.$server['http'].$server['site_url']);
        return 'Logging out, please wait while you are redirected to the homepage.';
    }    
}

?>