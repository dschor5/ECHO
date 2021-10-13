<?php

class AdminModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array(
            // Mission Settings
            'save_mission' => 'saveMissionSettings', 
            // Delay Settings
            'save_delay'   => 'saveDelaySettings', 
            // User Settings
            'getuser'      => 'getUser', 
            'edituser'     => 'editUser',
            'deleteuser'   => 'deleteUser', 
            'resetuser'    => 'resetUserPassword', 
            // Data Management
            'clear'        => 'clearMissionData', 
            'backupsql'    => 'backupSqlDatabase', 
            'saveconvo'    => 'saveConversationText',
            'savefiles'    => 'saveConversationFiles',
        );

        $this->subHtmlRequests = array(
            // Mission settings
            'mission'      => 'editMissionSettings', 
            // Delay settings
            'delay'        => 'editDelaySettings', 
            // User Settings
            'users'        => 'listUsers',
            // Data Management
            'data'         => 'editDataManagement',
            // Default
            'default'      => 'editMissionSettings'
        );
    }

    /***********************/
    /* Mission Settings    */
    /***********************/

    protected function saveMissionSettings(): array
    {
        $response = array(
            'success' => false, 
            'error'   => array()
        );

        $data = array();

        $STR_FMT    = '/^.+$/';
        $DATE_FMT   = '/^[\d]{4}-[\d]{2}-[\d]{2}$/';
        
        // List of fields to validate automatically. 
        $fields = array(
            'name'          => array('name'=>'Mission Name',              'format'=>$STR_FMT),
            'date_start'    => array('name'=>'Mission Start Date',        'format'=>$DATE_FMT),
            'date_end'      => array('name'=>'Mission End Date',          'format'=>$DATE_FMT),
            'mcc_name'      => array('name'=>'Mission Control Name',      'format'=>$STR_FMT),
            'mcc_planet'    => array('name'=>'Mission Control Planet',    'format'=>$STR_FMT),
            'mcc_user_role' => array('name'=>'Mission Control User Role', 'format'=>$STR_FMT),
            'mcc_timezone'  => array('name'=>'Mission Control Timezone',  'format'=>$STR_FMT),
            'hab_name'      => array('name'=>'Analog Habitat Name',       'format'=>$STR_FMT),
            'hab_planet'    => array('name'=>'Analog Habitat Planet',     'format'=>$STR_FMT),
            'hab_user_role' => array('name'=>'Analog Habitat User Role',  'format'=>$STR_FMT),
            'hab_timezone'  => array('name'=>'Analog Habitat Timezone',   'format'=>$STR_FMT),
        );

        foreach($fields as $name => $validation)
        {
            $temp = $_POST[$name] ?? '';
            $temp = trim($temp);
            if(strlen($temp) == 0)
            {  
                $response['error'][] = 'Field "'.$validation['name'].'" cannot be empty. ('.$temp.')';
            }
            elseif(!preg_match($validation['format'], $temp))
            {
                $response['error'][] = 'The format for "'.$validation['name'].'" is invalid. ('.$temp.')';
            }
            else
            {
                $data[$name] = $temp;
            }
        }

        // Additional checks if all required fields are filled and the right format.
        if(count($response['error']) == 0)
        {
            $data['date_start'] = $data['date_start'].' 00:00:00';
            $data['date_end'] = $data['date_end'].' 23:59:59';
            $dateStart = new DateTime($data['date_start']);
            $dateEnd = new DateTime($data['date_end']);
            if($dateStart->getTimestamp() > $dateEnd->getTimestamp()) 
            {
                $response['error'][] = 'The "Mission Start Date" cannot be after the "Mission End Date".';
            }

            $timezones = DateTimeZone::listIdentifiers();
            if(!in_array($data['mcc_timezone'], $timezones))
            {
                $response['error'][] = 'Invalid "Mission Control Timezone" selected.';
            }
            if(!in_array($data['hab_timezone'], $timezones))
            {
                $response['error'][] = 'Invalid "Analog Habitat Timezone" selected.';
            }
        }

        $response['success'] = count($response['error']) == 0;
        if($response['success'])
        {
            $mission = MissionConfig::getInstance();
            $data['date_start'] = DelayTime::convertTimestampTimezone(
                $data['date_start'], $mission->hab_timezone, 'UTC');
            $data['date_end'] = DelayTime::convertTimestampTimezone(
                $data['date_end'], $mission->hab_timezone, 'UTC');
            $missionDao = MissionDao::getInstance();
            $missionDao->updateMissionConfig($data);
        }
        
        return $response;
    }

    private function getTimezoneList()
    {
        $list = DateTimeZone::listAbbreviations();
        $idents = DateTimeZone::listIdentifiers();
        $data = array();
        foreach ($list as $key => $zones) {
            foreach ($zones as $id => $zone) {
                if ($zone['timezone_id'] and in_array($zone['timezone_id'], $idents)) {
                    $offset = round(abs($zone['offset'] / 3600));
                    $sign = $zone['offset'] > 0 ? '+' : '-';
                    if ($offset == 0) {
                        $sign = ' ';
                        $offset = '';
                    }
                    $zone['label'] = 'GMT' . $sign . $offset . ' - ' . $zone['timezone_id'];
                    $data[$zone['offset']][$zone['timezone_id']] = $zone;
                }
            }
        }
        ksort($data);
        $timezones = array();
        foreach ($data as $offsets) {
            ksort($offsets);
            foreach ($offsets as $zone) {
                $timezones[] = $zone;
            }
        }
        return $timezones;
    }

    protected function editMissionSettings() : string
    {
        $this->addTemplates('settings.css', 'settings.js', 'globalize.js', 'globalize.culture.de-DE.js');
        $mission = MissionConfig::getInstance();
        
        $timezoneData = $this->getTimezoneList();
        $mccTimezoneOptions = '';
        $habTimezoneOptions = '';
        foreach($timezoneData as $tz) 
        {
            $mccTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->mcc_timezone == $tz['timezone_id']);
            $habTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->hab_timezone == $tz['timezone_id']);
        }

        $missionStartDate = DelayTime::convertTimestampTimezone(
            $mission->date_start, 'UTC', $mission->hab_timezone);
        $missionEndDate = DelayTime::convertTimestampTimezone(
            $mission->date_end, 'UTC', $mission->hab_timezone);
        
        return Main::loadTemplate('admin-mission.txt', array(
            '/%name%/'            => $mission->name,
            '/%date_start%/'      => substr($missionStartDate, 0, 10),
            '/%date_end%/'        => substr($missionEndDate, 0, 10),
            '/%mcc_name%/'        => $mission->mcc_name,
            '/%mcc_planet%/'      => $mission->mcc_planet,
            '/%mcc_user_role%/'   => $mission->mcc_user_role,
            '/%mcc_timezone%/'    => $mccTimezoneOptions,
            '/%hab_name%/'        => $mission->hab_name,
            '/%hab_planet%/'      => $mission->hab_planet,
            '/%hab_user_role%/'   => $mission->hab_user_role,
            '/%hab_timezone%/'    => $habTimezoneOptions,
        ));
    }

    private function makeSelectOption(string $value, string $label, bool $selected)
    {
        return '<option value="'.$value.'" '.($selected ? 'selected="selected"' : '').'>'.$label.'</option>'.PHP_EOL;
    }    

    /***********************/
    /* Delay Settings      */
    /***********************/    

    private function isValidDelayEquationOfTime(string $eq)
    {
        $eq = preg_replace('/\s+/', '', $eq);

        $number = '(?:\d+(?:[,.]\d+)?|pi|π|time)'; // What is a number
        $functions = '(?:sinh?|cosh?|tanh?|abs|acosh?|asinh?|atanh?|exp|log10|deg2rad|rad2deg|sqrt|ceil|floor|round)'; // Allowed PHP functions
        $operators = '[+\/*\^%-]'; // Allowed math operators
        $regexp = '/^(('.$number.'|'.$functions.'\s*\((?1)+\)|\((?1)+\))(?:'.$operators.'(?2))?)+$/'; // Final regexp, heavily using recursive patterns

        return preg_match($regexp, $eq);
    }

    protected function saveDelaySettings(): array
    {
        $mission = MissionConfig::getInstance();

        $FLOAT_FMT  = '/^[\d]*[\.]?[\d]+$/';
        $DATE_FMT   = '/^[\d]{4}-[\d]{2}-[\d]{2}\s[\d]{2}:[\d]{2}:[\d]{2}$/';

        $response = array(
            'success' => false, 
            'error'   => array()
        );

        $data = array();

        if(isset($_POST['delay_is_manual']) && $_POST['delay_is_manual'] == 'true')
        {
            $data['delay_is_manual'] = '1';
            $temp = $_POST['delay_manual'] ?? '';
            $temp = trim($temp);
            if(!preg_match($FLOAT_FMT, $temp))
            {
                $response['error'][] = 'Invalid "Manual Delay" entered. Only numbers allowed.';
            }
            else
            {
                $time = new DelayTime();
                $delayConfig = array(array('ts'=>$time->getTime(), 'eq'=>floatval($temp)));
            }
        }
        elseif(isset($_POST['delay_is_manual']) && $_POST['delay_is_manual'] == 'false')
        {
            $data['delay_is_manual'] = '0';
            if(count($_POST['delay_time']) != count($_POST['delay_eq']) && 
               count($_POST['delay_time']) != count($_POST['delay_date']))
            {
                $response['error'][] = 'Invalid/incomplete piece-wise delay definitions.';
            }
            elseif(count($_POST['delay_time']) == 0)
            {
                $response['error'][] = 'At least one piece-wise definition is needed.';
            }
            else
            {
                for($i = 0; $i < count($_POST['delay_time']); $i++)
                {
                    $delayConfig[$i] = array(
                        'ts' => $_POST['delay_date'][$i].' '.$_POST['delay_time'][$i].':00', 
                        'eq' => $_POST['delay_eq'][$i]
                    );
                }
                
                for($i = 0; $i < count($_POST['delay_time']); $i++)
                {
                    if(!preg_match($DATE_FMT, $delayConfig[$i]['ts']))
                    {
                        $response['error'][] = 'Invalid piece-wise date/time entry in row '.($i+1).'.';
                    }
                    elseif(!$this->isValidDelayEquationOfTime($delayConfig[$i]['eq']))
                    {
                        $response['error'][] = 'Invalid piece-wise f(time) equation in row '.($i+1).'.';
                    }
                    else
                    {
                        $delayConfig[$i]['ts'] = DelayTime::convertTimestampTimezone(
                            $delayConfig[$i]['ts'], $mission->hab_timezone, 'UTC');
                    }
                }
            }
        }
        else
        {
            $response['error'][] = 'Field "Delay Configuration" cannot be empty.';
        }

        $response['success'] = count($response['error']) == 0;
        if($response['success'])
        {
            uasort($delayConfig, array('Delay', 'sortAutoDelay'));
            $data['delay_config'] = json_encode($delayConfig);
            $missionDao = MissionDao::getInstance();
            $missionDao->updateMissionConfig($data);
        }
        
        return $response;
    }

    protected function editDelaySettings() : string
    {
        $this->addTemplates('settings.css', 'settings.js', 'globalize.js', 'globalize.culture.de-DE.js');
        $mission = MissionConfig::getInstance();

        $delayIsManualOptions = 
            $this->makeSelectOption('true',  'Manual Delay Configuration',    $mission->delay_is_manual).
            $this->makeSelectOption('false', 'Automatic Delay Configuration', !$mission->delay_is_manual);

        if($mission->delay_is_manual)
        {
            $delayOptions = json_decode($mission->delay_config, true);
            $delayManual = floatval($delayOptions[0]['eq']);

            $delayAuto = Main::loadTemplate('admin-delay-config.txt', array(
                '/%delay-date-id%/'    => 'id="delay-date-0"',
                '/%delay-time-id%/'    => 'id="delay-time-0"',
                '/%delay-cfg-id%/'     => 'id="delay-cfg-0"',
                '/%delay-date-value%/' => substr($mission->date_start, 0, 10),
                '/%delay-time-value%/' => '00:00:00',
                '/%delay-cfg-value%/'  => 0,
            ));
        }
        else
        {
            $delayManual  = 0;
            $delayOptions = json_decode($mission->delay_config, true);
            $delayAuto    = '';
            foreach($delayOptions as $id => $cfg)
            {
                $cfg['ts'] = DelayTime::convertTimestampTimezone(
                    $cfg['ts'], 'UTC', $mission->hab_timezone);

                $dateTime = explode(' ', $cfg['ts']);

                $delayAuto .= Main::loadTemplate('admin-delay-config.txt', array(
                    '/%delay-date-id%/'    => 'id="delay-date-'.$id.'"',
                    '/%delay-time-id%/'    => 'id="delay-time-'.$id.'"',
                    '/%delay-cfg-id%/'     => 'id="delay-cfg-'.$id.'"',
                    '/%delay-date-value%/' => $dateTime[0],
                    '/%delay-time-value%/' => $dateTime[1],
                    '/%delay-cfg-value%/'  => $cfg['eq'],
                ));
            }
        }

        $delayAutoTemplate = Main::loadTemplate('admin-delay-config.txt', array(
            '/%delay-date-id%/'    => '',
            '/%delay-time-id%/'    => '',
            '/%delay-cfg-id%/'     => '',
            '/%delay-date-value%/' => '',
            '/%delay-time-value%/' => '',
            '/%delay-cfg-value%/'  => '',
        ));

        return Main::loadTemplate('admin-delay.txt', array(
            '/%delay_is_manual%/' => $delayIsManualOptions,
            '/%delay_manual%/'    => $delayManual,
            '/%delay_auto%/'      => $delayAuto,
            '/%delay_auto_tmp%/'  => $delayAutoTemplate,
        ));        
    }

    /***********************/
    /* User Settings       */
    /***********************/        

    protected function resetUserPassword()
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

    protected function deleteUser()
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

    protected function editUser()
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

    protected function getUser()
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

    protected function listUsers() : string
    {
        $this->addTemplates('settings.css', 'users.js');
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

        return Main::loadTemplate('admin-users.txt', array(
            '/%content%/'=>$list->build(),
            '/%role_mcc%/'=>$mission->mcc_user_role,
            '/%role_hab%/'=>$mission->hab_user_role,
        ));
    }


    protected function editDataManagement() : string 
    {
        $this->addTemplates('settings.css', 'data-management.js');
        return Main::loadTemplate('admin-data.txt');
    }

    
    protected function clearMissionData() : array
    {
        global $config;
        global $server;

        $messagesDao = MessagesDao::getInstance();
        $messagesDao->clearMessages();
        $files = scandir($server['host_address'].$config['uploads_dir']);
        foreach($files as $f)
        {
            if($f != '.' && $f != '..' && $f != 'dummy.txt')
            {
                unlink($server['host_address'].$config['uploads_dir'].'/'.$f);
            }
        }

        return array('success' => true);
    }

    protected function backupSqlDatabase() : array 
    {
        return array();
    }

    protected function saveConversationText() : array
    {
        return array();
    }

    protected function saveConversationFiles() : array
    {
        return array();
    }
}

?>