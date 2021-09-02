<?php 

class Delay
{
    private static $instance = null;
    private $currDelay = 0;
    const SPEED_OF_LIGHT_KM_P_SEC = 299.792458;
    const SEC_PER_MIN = 60;
    const SEC_PER_HOUR = 60 * 60;


    private function __construct()
    {
        $this->currDelay = 0;
    }

    public static function getInstance()
    {
        if(self::$instance == null)
        {
            self::$instance = new Delay();
        }
        return self::$instance;
    }

    public function getDelay(): float
    {
        $mission = MissionConfig::getInstance();
        if($mission->delay_is_manual)
        {
            $this->currDelay = floatval($mission->delay_config);
        }
        else
        {
            $config = json_decode($mission->delay_config, true);
            array_unshift($config, array('ts'=>'2000-00-00 00:00:00', 'eq'=>'0'));
            array_push($config, array('ts'=>'2100-01-01 00:00:00', 'eq'=>'0'));

            $i = 1;
            while(!(strtotime($config[$i]['ts']) < time() && time() < strtotime($config[$i+1]['ts'])) && $i < count($config)-1)
            {
                $i++;
            }

            try 
            {
                $metObj = new DelayTime();
                $config[$i]['eq'] = preg_replace('/time/', $metObj->getMet(), $config[$i]['eq']);
                eval('$this->currDelay = '.$config[$i]['eq'].';');
            } 
            catch (Exception $e) 
            {
                $this->currDelay = 0;
            }
            
        }
        return $this->currDelay;
    }

    public static function sortAutoDelay($a, $b)
    {
        $aObj = new DateTime($a['ts']);
        $bObj = new DateTime($b['ts']);
        $aTs = $aObj->getTimestamp();
        $bTs = $bObj->getTimestamp();

        if($aTs == $bTs)
        {
            return 0;
        }

        return ($aTs < $bTs) ? -1 : 1;
    }

    public function getDelayStr(): string
    {
        $delayStr = '';
        $currDelay = $this->getDelay();
        $hrs = intdiv($currDelay, self::SEC_PER_HOUR);
        $min = intdiv($currDelay - $hrs * self::SEC_PER_HOUR, self::SEC_PER_MIN);
        $sec = $this->currDelay - $hrs * self::SEC_PER_HOUR - $min * self::SEC_PER_MIN;

        if($hrs > 0)
        {
            $delayStr .= number_format($hrs).'hr ';
        }
        if($hrs > 0 || $min > 0)
        {
            $delayStr .= number_format($min).'min ';
        }

        return $delayStr.number_format($sec, 2).'sec';;
    }

    public function getDistance(): float
    {
        return $this->getDelay() * self::SPEED_OF_LIGHT_KM_P_SEC;
    }

    public function getDistanceStr(): string
    {
        return number_format($this->getDistance(),2)." km"; 
    }
}

?>