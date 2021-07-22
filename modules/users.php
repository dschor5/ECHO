<?php

require_once('index.php');
require_once('modules/default.php');

class UsersModule extends DefaultModule
{
    public function __construct(&$main, &$user)
    {
        parent::__construct($main, $user);
        $this->subJsonRequests = array();
        $this->subHtmlRequests = array('list', 'add', 'delete', 'edit');
    }

    public function compileJson(string $subaction): array
    {
        return array();
    }

    public function compileHtml(string $subaction) : string
    {
        $this->addCss('common');
        $this->addCss('settings');
        if($this->user->isAdmin())
        {
            $this->addHeaderMenu('Chat', 'chat');
            $this->addHeaderMenu('Mission Settings', 'mission');
        }

        $id = $_GET['id'] ?? 0;
        $usersDao = UsersDao::getInstance();
        
        $content = '';

        if($subaction == 'edit')
        {

        }
        else
        {
            if($subaction == 'delete' && $id > 0)
            {

            }

            $content .= $this->listUsers();   
        }

        return Main::loadTemplate('modules/settings.txt', array('/%content%/'=>$content));
    }

    private function listUsers() : string
    {
        $usersDao = UsersDao::getInstance();
        $users = $usersDao->getUsers();

        $headers = array(
            'id' => 'ID',
            'username' => 'Username',
            'is_crew'  => 'Is Crew?',
            'is_admin' => 'Is Admin?',
            'tools'    => 'Actions'
        );
        
        $list = new ListGenerator($headers);

        foreach($users as $id => $user)
        {
            $list->addRow(array(
                'id' => $id,
                'username' => $user->getUsername(),
                'is_crew' => $user->isCrew() ? 'YES' : 'NO',
                'is_admin' => $user->isAdmin() ? 'YES' : 'NO',
                'tools' => ''
            ));
        }

        return $list->build();
    }
}

?>