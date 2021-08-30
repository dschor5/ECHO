<?php

class UsersModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('getuser', 'edituser', 'deleteuser', 'resetuser');
        $this->subHtmlRequests = array('list');
    }

    public function compileJson(string $subaction): array
    {
        $response = array();
        switch($subaction)
        {
            case 'getuser':
            {
                $response = $this->getUser();
                break;   
            }
            case 'edituser':
            {
                $response = $this->editUser();
                break;
            }
            case 'deleteuser':
            {
                $response = $this->deleteUser();
                break;
            }
            case 'resetuser':
            {
                $response = $this->resetUserPassword();
                break;
            }
            default:
            {
                $response['success'] = false;
                $response['error'] = 'Unknown request.';
            }
        }

        return $response;
    }

    public function compileHtml(string $subaction) : string
    {
        $this->addTemplates('common.css', 'settings.css',
        'jquery-ui.css', 'jquery-ui.structure.css', 'jquery-ui.theme.css', 
        'jquery-3.6.0.min.js', 'jquery-ui.min.js', 'users.js');
        
        return $this->listUsers();
    }

    private function resetUserPassword()
    {
        global $server;
        $usersDao = UsersDao::getInstance();

        $response = array('success'=>false, 'error'=>'');

        $user_id = $_POST['user_id'] ?? 0;

        if($user_id > 0 && $user_id != $this->user->user_id)
        {
            if($usersDao->update(array('password_reset'=>1), $user_id) !== true)
            {
                $response['error'] = 'Failed to reset user password (user_id='.$user_id.').';
            } 
        }
        else
        {
            $response['error'] = 'Cannot reset your own account.';
        }

        return $response; 
    }

    private function deleteUser()
    {
        $usersDao = UsersDao::getInstance();
        
        $response = array('success'=>false, 'error'=>'');

        $userId = $_POST['user_id'] ?? 0;

        if($userId > 0 && $userId != $this->user->user_id)
        {
            // Delete associated images. 
            if($usersDao->deleteUser($userId) !== true)
            {
                $response['error'] = 'Failed to delete user. (user_id='.$userId.')';
            }
        }
        else
        {
            $response['error'] = 'Cannot delete yourself.';
        }

        return $response;
    }

    private function editUser()
    {
        $userId = $_POST['user_id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $alias = $_POST['alias'] ?? '';
        $isCrew = $_POST['is_crew'] ?? 1;
        $isAdmin = $_POST['is_admin'] ?? 0;

        $response = array('success'=>false, 'error'=>'');

        $usersDao = UsersDao::getInstance();
        $user = $usersDao->getByUsername($username);

        if($username == '' || strlen($username) < 4)
        {
            $response['error'] = 'Invalid username. Min 4 characters.';
        }
        elseif($user !== false && $user->user_id != $userId && $user->username == $username)
        {
            $response['error'] = 'Username already in use.';
        }
        elseif($user !== false && $user->is_admin != $isAdmin)
        {
            $response['error'] = 'Cannot remove your own admin priviledges.';
        }
        else
        {
            $fields = array(
                'username' => $username, 
                'alias'    => $alias,
                'is_crew'  => $isCrew,
                'is_admin' => $isAdmin
            );
            if($userId == 0)
            {
                global $admin;
                $fields['user_id'] = null;
                $fields['password'] = md5($admin['default_password']);
                $fields['password_reset'] = 1;
                
                if($usersDao->createNewUser($fields) === true)
                {
                    $response = array('success'=>true, 'error'=>'');
                }
                else
                {
                    $response['error'] = 'Failed to create new user (username='.$username.')';
                }
            }
            else
            {
                if($usersDao->update($fields, 'user_id='.$userId) === true)
                {
                    $response = array('success'=>true, 'error'=>'');
                }
                else
                {
                    $response['error'] = 'Failed to update user (user_id='.$userId.')';
                }
            }
        }

        return $response;
    }

    private function getUser()
    {
        $id = $_POST['user_id'] ?? 0;
        $usersDao = UsersDao::getInstance();
        
        $response = array('success'=>false);

        $user = $usersDao->getById($id);
        if($user != null)
        {
            $response = array(
                'success'  => true,
                'user_id'  => $id,
                'username' => $user->username,
                'alias'    => $user->alias,
                'is_admin' => $user->is_admin ? 1 : 0,
                'is_crew'  => $user->is_crew ? 1 : 0,
            );
        }

        return $response;
    }

    private function listUsers() : string
    {
        $mission = MissionConfig::getInstance();

        $sort = $_GET['sort'] ?? 'user_id';
        $order = $_GET['order'] ?? 'ASC';

        $usersDao = UsersDao::getInstance();
        $users = $usersDao->getUsers($sort, $order);
        
        $headers = array(
            'id' => 'ID',
            'username' => 'Username',
            'alias'    => 'Alias',
            'is_crew'  => 'Role',
            'is_admin' => 'Admin',
            'tools'    => 'Actions'
        );
        
        $list = new ListGenerator($headers);

        foreach($users as $id => $user)
        {
            $tools = array();
            $tools[] = Main::loadTemplate('link-js.txt', array(
                '/%onclick%/'=>'getUser('.$id.')', 
                '/%text%/'=>'Edit'
            ));

            if($this->user->user_id != $id)
            {
                $tools[] = Main::loadTemplate('link-js.txt', array(
                    '/%onclick%/'=>'confirmAction(\'deleteuser\', '.$id.', \''.$user->username.'\')', 
                    '/%text%/'=>'Delete'
                ));
                $tools[] = Main::loadTemplate('link-js.txt', array(
                    '/%onclick%/'=>'confirmAction(\'resetuser\', '.$id.', \''.$user->username.'\')', 
                    '/%text%/'=>'Reset Password'
                ));
            }

            $list->addRow(array(
                'id' => $id,
                'username' => $user->username,
                'alias' => $user->alias,
                'is_crew' => $user->is_crew ? $mission->hab_user_role : $mission->mcc_user_role,
                'is_admin' => $user->is_admin ? 'Yes' : 'No',
                'tools' => join(', ', $tools),
            ));
        }

        return Main::loadTemplate('users.txt', array(
            '/%content%/'=>$list->build(),
            '/%role_mcc%/'=>$mission->mcc_user_role,
            '/%role_hab%/'=>$mission->hab_user_role,
        ));
    }
}

?>