<?php

class SettingsModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('save');
        $this->subHtmlRequests = array('show');
    }

    public function compileJson(string $subaction): array
    {
        $name = $_POST['name'] ?? '';
        $date_start = $_POST['date_start'] ?? '0000-00-00';
        $date_end = $_POST['date_end'] ?? '0000-00-00';
        $mcc_name = $_POST['mcc_name'] ?? '';
        $mcc_planet = $_POST['mcc_planet'] ?? '';
        $mcc_user_role = $_POST['mcc_user_role'] ?? '';
        $mcc_timezone = $_POST['mcc_timezone'];
        $hab_name = $_POST['hab_name'] ?? '';
        $hab_planet = $_POST['hab_planet'] ?? '';
        $hab_user_role = $_POST['hab_user_role'] ?? '';
        $hab_timezone = $_POST['hab_timezone'];
        $delay_is_manual = $_POST['delay_is_manual'] ?? false;
        
        if(strlen($name) == 0) 
        {
            $response['error'] = 'Mission name cannot be empty.';
        }
        
        return array();
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
            $delayManual = intval($mission->delay_config);
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