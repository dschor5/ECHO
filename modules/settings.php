<?php

class SettingsModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('save');
        $this->subHtmlRequests = array('show');
    }

    private function isValidDelayEquationOfTime(string $eq)
    {
        $eq = preg_replace('/\s+/', '', $test);

        $number = '(?:\d+(?:[,.]\d+)?|pi|π|time)'; // What is a number
        $functions = '(?:sinh?|cosh?|tanh?|abs|acosh?|asinh?|atanh?|exp|log10|deg2rad|rad2deg|sqrt|ceil|floor|round)'; // Allowed PHP functions
        $operators = '[+\/*\^%-]'; // Allowed math operators
        $regexp = '/^(('.$number.'|'.$functions.'\s*\((?1)+\)|\((?1)+\))(?:'.$operators.'(?2))?)+$/'; // Final regexp, heavily using recursive patterns
        
        return preg_match($regexp, $eq);
    }

    public function compileJson(string $subaction): array
    {
        $response = array(
            'success' => false, 
            'error'   => array()
        );

        $data = array();

        $STR_FMT    = '/^.+$/';
        $DATE_FMT   = '/^[\d]{4}-[\d]{2}-[\d]{2}$/';
        $BOOL_FMT   = '/^[0-1]$/';
        $FLOAT_FMT  = '/^[\d]*[\.]?[\d]+$/';

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
            'delay_is_manual' => array('name'=>'Delay Configuration',     'format'=>$BOOL_FMT),
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

        if(isset($data['delay_is_manual']) && $data['delay_is_manual'] == '1')
        {
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
        elseif(isset($data['delay_is_manual']) && $data['delay_is_manual'] == '0')
        {
            
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
        $mission = MissionConfig::getInstance();
        $this->addTemplates('settings.css', 'settings.js');

        $timezoneData = $this->getTimezoneList();
        $mccTimezoneOptions = '';
        $habTimezoneOptions = '';
        foreach($timezoneData as $tz) 
        {
            $mccTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->mcc_timezone == $tz['timezone_id']);
            $habTimezoneOptions .= $this->makeSelectOption($tz['timezone_id'], $tz['label'], $mission->hab_timezone == $tz['timezone_id']);
        }

        $delayIsManualOptions = 
            $this->makeSelectOption(1, 'Manual Delay Configuration', $mission->delay_is_manual).
            $this->makeSelectOption(0, 'Automatic Delay Configuration', !$mission->delay_is_manual);

        if($mission->delay_is_manual)
        {
            $delayManual = floatval($mission->delay_config);
            $delayAuto   = Main::loadTemplate('delay-config.txt', array(
                '/%delay-met-id%/'    => 'id="delay-met-0"',
                '/%delay-cfg-id%/'    => 'id="delay-cfg-0"',
                '/%delay-met-value%/' => substr($mission->date_start, 0, 10),
                '/%delay-cfg-value%/' => 0,
            ));
        }
        else
        {
            $delayManual  = 0;
            $delayOptions = json_decode($mission->delay_config);
            $delayAuto    = '';
            foreach($delayOptions as $id => $cfg)
            {
                $delayAuto .= Main::loadTemplate('delay-config.txt', array(
                    '/%delay-met-id%/'    => 'id="delay-met-'.$id.'"',
                    '/%delay-cfg-id%/'    => 'id="delay-cfg-'.$id.'"',
                    '/%delay-met-value%/' => $cfg['met'],
                    '/%delay-cfg-value%/' => $cfg['cfg'],
                ));
            }
        }

        $delayAutoTemplate = Main::loadTemplate('delay-config.txt', array(
            '/%delay-met-id%/'    => '',
            '/%delay-cfg-id%/'    => '',
            '/%delay-met-value%/' => '',
            '/%delay-cfg-value%/' => '',
        ));
        
        return Main::loadTemplate('settings.txt', array(
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
            '/%delay_is_manual%/' => $delayIsManualOptions,
            '/%delay_manual%/'    => $delayManual,
            '/%delay_auto%/'      => $delayAuto,
            '/%delay_auto_tmp%/'  => $delayAutoTemplate,
        ));
    }

    private function makeSelectOption(string $value, string $label, bool $selected)
    {
        return '<option value="'.$value.'" '.($selected ? 'selected="selected"' : '').'>'.$label.'</option>'.PHP_EOL;
    }

}

?>