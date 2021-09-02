<?php

class SettingsModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('save_mission', 'save_delay');
        $this->subHtmlRequests = array('mission', 'delay');
    }

    private function isValidDelayEquationOfTime(string $eq)
    {
        $eq = preg_replace('/\s+/', '', $eq);

        $number = '(?:\d+(?:[,.]\d+)?|pi|π|time)'; // What is a number
        $functions = '(?:sinh?|cosh?|tanh?|abs|acosh?|asinh?|atanh?|exp|log10|deg2rad|rad2deg|sqrt|ceil|floor|round)'; // Allowed PHP functions
        $operators = '[+\/*\^%-]'; // Allowed math operators
        $regexp = '/^(('.$number.'|'.$functions.'\s*\((?1)+\)|\((?1)+\))(?:'.$operators.'(?2))?)+$/'; // Final regexp, heavily using recursive patterns
        
        

        return preg_match($regexp, $eq);
    }

    public function compileJson(string $subaction): array
    {
        $response = array();

        if($subaction == 'save_mission')
        {
            $response = $this->saveMissionSettings();
        }
        else if($subaction == 'save_delay')
        {
            $response = $this->saveDelaySettings();
        }

        return $response;
    }

    private function saveDelaySettings(): array
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
            $temp = trim($_POST['delay_manual'] ?? '');
            if(!preg_match($FLOAT_FMT, $temp))
            {
                $response['error'][] = 'Invalid "Manual Delay" entered. Only numbers allowed.';
            }
            else
            {
                $data['delay_config'] = floatval($temp);
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

    private function saveMissionSettings(): array
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
            $response['saved'] = 1;
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

    public function compileHtml(string $subaction) : string
    {
        $content = '';

        $this->addTemplates('settings.css', 'settings.js', 'globalize.js', 'globalize.culture.de-DE.js');
        
        if($subaction == 'mission')
        {
            $content = $this->editMissionSettings();
        }
        else
        {
            $content = $this->editDelaySettings();
        }

        return $content;
    }

    private function editDelaySettings() : string
    {
        $mission = MissionConfig::getInstance();

        $delayIsManualOptions = 
            $this->makeSelectOption('true',  'Manual Delay Configuration',    $mission->delay_is_manual).
            $this->makeSelectOption('false', 'Automatic Delay Configuration', !$mission->delay_is_manual);

        if($mission->delay_is_manual)
        {
            $delayManual = floatval($mission->delay_config);

            $delayAuto = Main::loadTemplate('delay-config.txt', array(
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

                $delayAuto .= Main::loadTemplate('delay-config.txt', array(
                    '/%delay-date-id%/'    => 'id="delay-date-'.$id.'"',
                    '/%delay-time-id%/'    => 'id="delay-time-'.$id.'"',
                    '/%delay-cfg-id%/'     => 'id="delay-cfg-'.$id.'"',
                    '/%delay-date-value%/' => $dateTime[0],
                    '/%delay-time-value%/' => $dateTime[1],
                    '/%delay-cfg-value%/'  => $cfg['eq'],
                ));
            }
        }

        $delayAutoTemplate = Main::loadTemplate('delay-config.txt', array(
            '/%delay-date-id%/'    => '',
            '/%delay-time-id%/'    => '',
            '/%delay-cfg-id%/'     => '',
            '/%delay-date-value%/' => '',
            '/%delay-time-value%/' => '',
            '/%delay-cfg-value%/'  => '',
        ));

        return Main::loadTemplate('settings-delay.txt', array(
            '/%delay_is_manual%/' => $delayIsManualOptions,
            '/%delay_manual%/'    => $delayManual,
            '/%delay_auto%/'      => $delayAuto,
            '/%delay_auto_tmp%/'  => $delayAutoTemplate,
        ));        
    }

    private function editMissionSettings() : string
    {
        $mission = MissionConfig::getInstance();
        
        $timezoneData = $this->getTimezoneList();
        $mccTimezoneOptions = '';
        $habTimezoneOptions = '';
        foreach($timezoneData as $tz) 
        {
            $mccTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->mcc_timezone == $tz['timezone_id']);
            $habTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->hab_timezone == $tz['timezone_id']);
        }
        
        return Main::loadTemplate('settings-mission.txt', array(
            '/%name%/'            => $mission->name,
            '/%date_start%/'      => substr($mission->date_start, 0, 10),
            '/%date_end%/'        => substr($mission->date_end, 0, 10),
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

}

?>