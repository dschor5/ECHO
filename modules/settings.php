<?php

class SettingsModule extends DefaultModule
{
    public function __construct(&$user)
    {
        parent::__construct($user);
        $this->subJsonRequests = array('validate', 'save');
        $this->subHtmlRequests = array('show');
    }

    public function compileJson(string $subaction): array
    {
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
            $mccTimezoneOptions .= '<option value="'.$tz['timezone_id'].'">'.$tz['label'].'</option>';
            if($mission->mcc_timezone == $tz['timezone_id'])
            {
                $mccTimezoneOptions .= '<option value="'.$tz['timezone_id'].'" selected="selected">'.$tz['label'].'</option>';
            }
            
            $habTimezoneOptions .= '<option value="'.$tz['timezone_id'].'">'.$tz['label'].'</option>';
            if($mission->hab_timezone == $tz['timezone_id'])
            {
                $habTimezoneOptions .= '<option value="'.$tz['timezone_id'].'" selected="selected">'.$tz['label'].'</option>';
            }
        }
        
        return Main::loadTemplate('settings.txt', array(
            '/%name%/'          => $mission->name,
            '/%date_start%/'    => substr($mission->date_start, 0, 10),
            '/%date_end%/'      => substr($mission->date_end, 0, 10),
            '/%mcc_name%/'      => $mission->mcc_name,
            '/%mcc_planet%/'    => $mission->mcc_planet,
            '/%mcc_user_role%/' => $mission->mcc_user_role,
            '/%mcc_timezone%/'  => $mccTimezoneOptions,
            '/%hab_name%/'      => $mission->hab_name,
            '/%hab_planet%/'    => $mission->hab_planet,
            '/%hab_user_role%/' => $mission->hab_user_role,
            '/%hab_timezone%/'  => $habTimezoneOptions,
        ));
    }

}

?>