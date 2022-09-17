<?php

class AdminModule extends DefaultModule
{
    const TIMEOUT_OPS_SEC = array(
          '1800' => '30 min', 
          '3600' => '60 min (1 hr)', 
          '7200' => '120 min (2 hr)', 
         '86400' => '1440 min (24 hr)', 
        '172800' => '2880 min (48 hr)', 
        '604800' => '10080 min (7 days)'
    );

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
            'resetlog'     => 'resetSystemLog', 
            'backupsql'    => 'backupSqlDatabase', 
            'backupconvo'  => 'backupConversations',
            'backuplog'    => 'backupSystemLog',
            'deletearchive'=> 'deleteArchive',   
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
            'default'      => 'editMissionSettings',
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
            'login_timeout' => array('name'=>'Config Timeout',            'format'=>$STR_FMT),
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

        $featureEnableState = array(
            'feat_audio_notification',
            'feat_badge_notification',
            'feat_unread_msg_counts',
            'feat_convo_list_order',
            'feat_est_delivery_status',
            'feat_progress_bar',
            'feat_markdown_support',
            'feat_important_msgs',
            'feat_convo_threads',    
            'debug'        
        );

        foreach($featureEnableState as $feature)
        {
            $data[$feature] = $_POST[$feature] ?? '0';
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

            if(!array_key_exists($data['login_timeout'], self::TIMEOUT_OPS_SEC))
            {
                $response['error'][] = 'Invalid "Login Timeout" selected.';
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

            $messagesDao = MessagesDao::getInstance();
            if($data['feat_convo_threads'] != $mission->feat_convo_threads)
            {
                // Turning off threads
                if($data['feat_convo_threads'] == '0')
                {                    
                    $messagesDao->renumberSiteMessageId(false);
                }
                // Enabling threads
                else
                {
                    $messagesDao->renumberSiteMessageId(true);
                }
            }

            $missionDao = MissionDao::getInstance();
            $missionDao->updateMissionConfig($data);
            Logger::info('Save mission settings.', $data);
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
        $this->addTemplates('settings.css', 'admin.js', 'globalize.js', 'globalize.culture.de-DE.js');
        $mission = MissionConfig::getInstance();
        
        $timezoneData = $this->getTimezoneList();
        $mccTimezoneOptions = '';
        $habTimezoneOptions = '';
        foreach($timezoneData as $tz) 
        {
            $mccTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->mcc_timezone == $tz['timezone_id']);
            $habTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->hab_timezone == $tz['timezone_id']);
        }

        $timeoutOptions = '';
        foreach(self::TIMEOUT_OPS_SEC as $timeout_sec => $timeout_label)
        {
            $timeoutOptions .= $this->makeSelectOption($timeout_sec, $timeout_label, $mission->login_timeout == intval($timeout_sec));
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
            '/%timeout-options%/' => $timeoutOptions,
            '/%feat_audio_notification_checked%/'  => $mission->feat_audio_notification  == '1' ? 'checked' : '',
            '/%feat_badge_notification_checked%/'  => $mission->feat_badge_notification  == '1' ? 'checked' : '',
            '/%feat_unread_msg_counts_checked%/'   => $mission->feat_unread_msg_counts   == '1' ? 'checked' : '',
            '/%feat_convo_list_order_checked%/'    => $mission->feat_convo_list_order    == '1' ? 'checked' : '',
            '/%feat_est_delivery_status_checked%/' => $mission->feat_est_delivery_status == '1' ? 'checked' : '',
            '/%feat_progress_bar_checked%/'        => $mission->feat_progress_bar        == '1' ? 'checked' : '',
            '/%feat_markdown_support_checked%/'    => $mission->feat_markdown_support    == '1' ? 'checked' : '',
            '/%feat_important_msgs_checked%/'      => $mission->feat_important_msgs      == '1' ? 'checked' : '',
            '/%feat_convo_threads_checked%/'       => $mission->feat_convo_threads       == '1' ? 'checked' : '',
            '/%debug_checked%/'                    => $mission->debug                    == '1' ? 'checked' : '',
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
                $currTimeObj = new DelayTime();
                $currTime = $currTimeObj->getTimestamp();

                $startTime = DelayTime::getStartTimeUTC();
                $endTime = DelayTime::getEndTimeUTC();

                if($startTime < $currTime && $currTime <= $endTime)
                {
                    $delayConfig = array_merge(
                        json_decode($mission->delay_config, true), 
                        array(array('ts'=>$currTimeObj->getTime(), 'eq'=>floatval($temp)))
                    );
                }
                else
                {
                    $delayConfig = array(array('ts'=>$currTimeObj->getTime(), 'eq'=>floatval($temp)));
                }
                
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
            Logger::info('Saved delay settings.', $delayConfig);
        }
        
        return $response;
    }

    protected function editDelaySettings() : string
    {
        $this->addTemplates('settings.css', 'admin.js', 'globalize.js', 'globalize.culture.de-DE.js');
        $mission = MissionConfig::getInstance();

        $delayIsManualOptions = 
            $this->makeSelectOption('true',  'Manual Delay Configuration',    $mission->delay_is_manual).
            $this->makeSelectOption('false', 'Automatic Delay Configuration', !$mission->delay_is_manual);

        if($mission->delay_is_manual)
        {
            $delayManual = floatval(Delay::getInstance()->getDelay());

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
            global $admin;
            $fields = array();
            $fields['password'] = User::encryptPassword($admin['default_password']);
            $fields['is_password_reset'] = 1;

            if($usersDao->update($fields, $user_id) !== true)
            {
                $response['error'] = 'Failed to reset user password (user_id='.$user_id.').';
            } 
            else
            {
                Logger::info('Force password reset for user_id='.$user_id);
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
            else
            {
                Logger::info('Deleted user_id='.$userId);
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

        if($username == '' || strlen($username) < 4 || strlen($username) > 12 || !ctype_alnum($username))
        {
            $response['error'] = 'Invalid username. Requires min 4 / max 12 alphanumeric characters.';
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
                $fields['password'] = User::encryptPassword($admin['default_password']);
                $fields['is_password_reset'] = 1;
                
                if(($userId = $usersDao->createNewUser($fields)) > 0)
                {
                    $response = array('success'=>true, 'error'=>'');
                    unset($fields['password']);
                    $fields['user_id'] = $userId;
                    Logger::info('Created new user "'.$fields['username'].'"', $fields);
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
                    Logger::info('Updated user_id='.$userId, $fields);
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
                'username' => htmlspecialchars($user->username),
                'alias' => htmlspecialchars($user->alias),
                'is_crew' => $user->is_crew ? $mission->hab_user_role : $mission->mcc_user_role,
                'is_admin' => $user->is_admin ? 'Yes' : 'No',
                'tools' => join(', ', $tools),
            ));
        }

        return Main::loadTemplate('admin-users.txt', array(
            '/%content%/'=>$list->build(),
            '/%role_mcc%/'=>htmlspecialchars($mission->mcc_user_role),
            '/%role_hab%/'=>htmlspecialchars($mission->hab_user_role),
        ));
    }


    protected function editDataManagement() : string 
    {
        
        $this->addTemplates('settings.css', 'data-management.js');
        $mission = MissionConfig::getInstance();

        $timezoneData = $this->getTimezoneList();
        $archiveTzOptions = '';
        foreach($timezoneData as $tz) 
        {
            $archiveTzOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->mcc_timezone == $tz['timezone_id']);
        }
        
        $headersZip = array(
            'id' => 'ID',
            'type' => 'Archive Type',
            'timestamp' => 'Date Created (MCC Timezone)',
            'tools' => 'Actions'
        );
        
        $list = new ListGenerator($headersZip);

        $archiveDao = ArchiveDao::getInstance();
        $archives = $archiveDao->getArchives();

        foreach($archives as $id => $archive)
        {
            $tools = array();
            $tools[] = Main::loadTemplate('link-url.txt', array(
                '/%path%/'=>'archive/'.$archive->archive_id, 
                '/%text%/'=>'Download'
            ));
            $tools[] = Main::loadTemplate('link-js.txt', array(
                '/%onclick%/'=>'confirmAction(\'deletearchive\', '.$id.', \''.$archive->getDesc().'\')', 
                '/%text%/'=>'Delete'
            ));

            $list->addRow(array(
                'id' => $id,
                'type' => $archive->getType(),
                'timestamp' => $archive->getTimestamp(),
                'tools' => join(', ', $tools),
            ));
        }

        // Get a copy of the system log to display
        $logNum = 50;
        $logEntries = Logger::tailLog($logNum);

        return Main::loadTemplate('admin-data.txt', array(
            '/%archive_tz%/'=>$archiveTzOptions,
            '/%archives%/' => $list->build(),
            '/%log-num%/' => $logNum,
            '/%log-entries%/' => $logEntries,
        ));
    }

    
    protected function clearMissionData() : array
    {
        global $config;
        global $server;

        $messagesDao = MessagesDao::getInstance();
        $messagesDao->clearMessagesAndThreads(); 
        $files = scandir($server['host_address'].$config['uploads_dir']);
        foreach($files as $f)
        {
            if($f != '.' && $f != '..' && $f != 'dummy.txt')
            {
                unlink($server['host_address'].$config['uploads_dir'].'/'.$f);
            }
        }
        Logger::info("Cleared log.");

        return array('success' => true);
    }

    /**
     * Create a backup of the MySQL database. 
     *
     * @return array 
     */
    protected function backupSqlDatabase() : array 
    {
        global $config;
        global $server;
        global $database;
        
        $response = array(
            'success' => true,
        );

        $currTime = new DelayTime();
        $archiveData = array();
        $archiveData['archive_id'] = 0;
        $archiveData['server_name'] = ServerFile::generateFilename($config['logs_dir']);
        $archiveData['notes'] = ''; // Not used for SQL archives.
        $archiveData['mime_type'] = 'application/sql';
        $archiveData['timestamp'] = $currTime->getTime();
        $archiveData['content_tz'] = 'UTC';

        $filePath    = $server['host_address'].$config['logs_dir'].'/'.$archiveData['server_name'];

        $command = 'mysqldump --no-tablespaces'. 
                            ' --host=\''.$database['db_host'].'\''.
                            ' --user=\''.$database['db_user'].'\''.
                            ' --password=\''.$database['db_pass'].'\''.
                            ' \''.$database['db_name'].'\''.
                            ' > '.$filePath;
        $startTime = microtime(true);
        exec($command, $output, $worked);
        $response['time'] = microtime(true) - $startTime;

        if($worked == 0)
        {
            $archiveDao = ArchiveDao::getInstance();
            $result = $archiveDao->insert($archiveData);
            
            if($result === false)
            {
                unlink($filePath);
                Logger::warning('admin::backupSqlDatabase failed to add archive to database.');
                $response['success'] = false;
                $response['error'] = 'Failed to create archive. See system log for details.';
            }
        }
        else
        {
            Logger::warning('admin::backupSqlDatabase failed to create "'.$archiveData['server_name'].'"', 
                array('output'=>$output, 'worked'=>$worked));
            unlink($filePath);    
            $response['success'] = false;
            $response['error'] = 'Failed to create archive. See system log for details.';
        }

        Logger::info('admin::backupSqlDatabase finished for "'. $archiveData['server_name'].
        '" in '.$response['time'].' sec. ('.$result.')');

        return $response;
    }

    protected function resetSystemLog() : array
    {
        global $config;
        global $server;

        $response = array(
            'success' => true,
        );

        $filepath = $server['host_address'].$config['logs_dir'].'/'.$config['log_file'];
        if(file_exists($filepath))
        {
            $response['success'] = unlink($filepath);
        }
        Logger::info("Reset System Log.");

        return $response;
    }

    /**
     * Create a backup of the System Log. 
     *
     * @return array 
     */
    protected function backupSystemLog() : array 
    {
        global $config;
        global $server;
        
        $currTime = new DelayTime();
        $archiveData = array();
        $archiveData['archive_id'] = 0;
        $archiveData['server_name'] = ServerFile::generateFilename($config['logs_dir']);
        $archiveData['notes'] = ''; // Not used for SQL archives.
        $archiveData['mime_type'] = 'application/txt';
        $archiveData['timestamp'] = $currTime->getTime();
        $archiveData['content_tz'] = 'UTC';

        $fromPath = $server['host_address'].$config['logs_dir'].'/'.$config['log_file'];
        $toPath = $server['host_address'].$config['logs_dir'].'/'.$archiveData['server_name'];

        if(copy($fromPath, $toPath))
        {
            $archiveDao = ArchiveDao::getInstance();
            $result = $archiveDao->insert($archiveData);
            
            if($result === false)
            {
                unlink($toPath);
                Logger::warning('admin::backupSystemLog failed to add archive to database.');
                $response['success'] = false;
                $response['error'] = 'Failed to create archive. See system log for details.';
            }
        }
        else
        {
            Logger::warning('admin::backupSystemLog failed to create "'.$archiveData['server_name'].'"');
            unlink($toPath);    
            $response['success'] = false;
            $response['error'] = 'Failed to create archive. See system log for details.';
        }

        Logger::info('admin::backupSystemLog finished for "'. $archiveData['server_name'].'". ('.$result.')');

        return $response;
    }

    protected function backupConversations() : array
    {
        global $config;
        global $server;

        $response = array(
            'success' => true, 
            'time'    => 0, 
            'error'   => '', 
        );

        $tzSelected = $_POST['timezone'] ?? '';
        $isCrew = !(isset($_POST['perspective']) && $_POST['perspective'] == 'mcc');
        $timezones = DateTimeZone::listIdentifiers();
        if(!in_array($tzSelected, $timezones))
        {
            $response['success'] = false;
            $response['error'] = 'Invalid "Timezone" selected.';
        }
        else
        {
            $currTime = new DelayTime();
            $archiveData = array();
            $archiveData['archive_id'] = 0;
            $archiveData['server_name'] = ServerFile::generateFilename($config['logs_dir']);
            $archiveData['notes'] = ''; // Placeholder to save options used for creating archive (if any).
            $archiveData['mime_type'] = 'application/zip';
            $archiveData['timestamp'] = $currTime->getTime();
            $archiveData['content_tz'] = $tzSelected;

            Logger::info('admin::backupConversations started for "'.$archiveData['server_name'].'"');
            $startTime = microtime(true);

            $zipFilepath = $server['host_address'].$config['logs_dir'].'/'.$archiveData['server_name'];

            $conversationsDao = ConversationsDao::getInstance();
            $conversations = $conversationsDao->getConversations();
            
            $zip = new ZipArchive();
            if(!$zip->open($zipFilepath, ZipArchive::CREATE)) 
            {
                Logger::warning('admin::backupConversations failed to create "'. $archiveData['server_name'].'"');
            }      
            else
            {
                $mission = MissionConfig::getInstance();
                $sepThreads = $mission->feat_convo_threads;

                foreach($conversations as $convoId => $convo)
                {
                    $parentName = ($convo->parent_conversation_id != null) ? 
                        $conversations[$convo->parent_conversation_id]->name : '';
                    if(!$sepThreads && $convo->parent_conversation_id != null)
                    {
                        continue;
                    }

                    if(!$convo->archiveConvo($zip, $tzSelected, $sepThreads, $parentName, $isCrew))
                    {
                        Logger::warning('conversation::archiveConvo failed to save '.$convoId.'.');
                        $response['success'] = false;
                        break;
                    }
                }
                $zip->close();
                $response['time'] = microtime(true) - $startTime;

                if($response['success'] === true)
                {
                    $archiveDao = ArchiveDao::getInstance();
                    $result = $archiveDao->insert($archiveData);

                    if($result === false)
                    {
                        unlink($zipFilepath);
                        Logger::warning('conversation::saveArchive failed to add archive to database.');
                        $response['success'] = false;
                        $response['error'] = 'Failed to create archive. See system log for details.';
                    }
                }
                else
                {
                    unlink($zipFilepath);
                    $response['success'] = false;
                    $response['error'] = 'Failed to create archive. See system log for details.';
                }
            }
        }
        
        Logger::info('admin::backupConversations finished for "'. $archiveData['server_name'].
            '" in '.$response['time'].' sec. ('.$result.')');

        return $response;
    }

    protected function deleteArchive()
    {
        global $config;
        global $server;
        $archiveDao = ArchiveDao::getInstance();
        
        $response = array('success'=>false, 'error'=>'');

        $archiveId = $_POST['archive_id'] ?? 0;

        if($archiveId > 0)
        {
            $archive = $archiveDao->getArchive($archiveId, $this->user->user_id);
            if($archive != null)
            {
                $filepath = $server['host_address'].$config['logs_dir'].'/'.$archive->server_name;
                
                if(!unlink($filepath))
                {
                    Logger::warning('admin::deleteArchive failed to delete '.$archive->server_name.'.');
                    $response['error'] = 'Failed to delete archive. (archive_id='.$archiveId.')';
                }
                else if($archiveDao->drop($archiveId) !== true)
                {
                    Logger::warning('admin::deleteArchive failed to delete '.$archive->archive_id.' db entry.');
                    $response['error'] = 'Failed to delete archive. (archive_id='.$archiveId.')';
                }
                else
                {
                    $response['success'] = true;
                    Logger::info('Delete archive_id='.$archiveId);
                }
            }
            else
            {
                Logger::warning('admin::deleteArchive failed to delete '.$archiveId.' (obj null).');
            }
        }
        else
        {
            Logger::warning('admin::deleteArchive '.$archiveId.' <= 0.');
        }

        return $response;
    }
}

?>
