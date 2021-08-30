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
        $this->addTemplates('settings.css', 'settings.js');

        $timezoneData = $this->getTimezoneList();
        $timezone = '';
        
        return Main::loadTemplate('settings.txt', array(
            '/%timezones%/' => $timezone,
        ));
    }

}

?>