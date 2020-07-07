<?php
/**
 * Орбита планеты
 * последовательность вызовов:
 * настройка: $orbit->setOrbitType(..)   - тип орбиты (круговая|эллиптическая
                 -> setPlanetId(..)      - ид планеты (Земля|Луна)
 *               -> setTestDT(..)        - тестовый момент для выбора параметров орбиты
 * вычисление: $orbit->getTheta($dt)   - центральный угол точки орбиты
 *                                       в момент dt
 */

class Orbit extends Common
{
    protected $orbitType ;            // тип орбиты (круговая | эллиптическая)
    protected $planetId ;             // планета
    private $orbitTypeObj ;           // объект <-> типу орбиты
//    protected $dateT0 = ['y'=>2019,'m'=> 3,'d'=> 21,'h'=>10.25] ;  // начальный момент отсчёта орбиты
//    protected $theta0 = 0 ;   // начальный угол <-> $dateT0
    protected $dateT = '2019-11-23' ;   // текущая дата
    protected $theta = 0 ;              // тек угол, соотв точке на орбите
    protected $parObjects = [] ;              // ссылки на объект - набор параметров
    protected $sourcePar = [    // параметры из исходных данных
        'period' => ['dBeg' => '','dEnd'=> '',
            'd0' => '',      // дата - начало отсчёта (Земля-равноденствие; Луна - новолуние)
            'T'=>0.00,'default'=>false],
        'ellipse' => ['peri'=>['dBeg'=>'','dEnd'=>'','r'],
            'apo'=>['dBeg'=>'','dEnd'=>'','r'],

            'default' => false],
    ] ;
    protected $orbitPar = ['tsBeg' => 0,'tsEnd'=> 0,
        'd0' => 0,     //  начальный момент, в который  theta = 0
        'theta0' => 0.00,  // угол, соотв ts0, - смещение по отношение tsBeg
                           // для Солнца - tBeg=tPeri -> thBeg = 0;
                           // t0 = Tравноденствие -> th0 = угол по отнош перигелию
                           // для Луны: t0 - полнолуние
        'T'=>0.00] ;         // суток
    protected $ellipsePar = [
        'rPeri' => 0, // перицентр
        'rApo' => 0,  // апоцентр
    ] ;
    //--------------------------------------------------//
    public function __construct() {
        $this->planetId = self::OBJECT_ID_EARTH ;
        $this->orbitType = self::ORBIT_TYPE_CIRCLE ;
    }

    public function setOrbitType($oType) {
        $tList = [self::ORBIT_TYPE_CIRCLE, self::ORBIT_TYPE_ELLIPT] ;
        if (in_array($oType,$tList) ) {
            $this->orbitType = $oType ;
        }
        return $this ;
    }
    public function setPlanetId($pId) {
        $pList = [self::OBJECT_ID_EARTH, self::OBJECT_ID_MOON] ;
        if (in_array($pId,$pList) ) {
            $this->planetId = $pId ;
            if (!isset($this->parObjects[$this->planetId])) {
                if ($this->planetId === self::OBJECT_ID_EARTH) {
                    $oPar = new EarthPar();
//                $this->parObjects[$this->planetId] = new EarthPar();
                } else {
                    $oPar = new MoonPar(); ;
//                $this->parObjects[$this->planetId] = new MoonPar();
                }
                $this->parObjects[$this->planetId] = $oPar ;
            }
        }
        return $this ;
    }

    /**
     * вынужденная заплатка для возможности корректировки
     * параметров, получаемых из oPar->getParClc
     * запускать строго после setPlanetId
     */
    public function setParTuning($parTuning) {
        $oPar = $this->parObjects[$this->planetId];
        $oPar->setParTuning($parTuning) ;
        return $this ;
    }
     /**
     * по текущей дате будут перевыбраны параметры орбиты
     * @param $dT
     * @return $this
     */
    public function setTestDT($dT) {
            $this->dateT = $dT ;
            $ts = strtotime($dT) ;
            $tsBeg = $this->orbitPar['tsBeg'] ;
            $tsEnd = $this->orbitPar['tsEnd'] ;
            if ($ts < $tsBeg || $ts > $tsEnd) {
                $this->clcParms() ;
            }
            // установка параметров орбиты в зависимости от типа
            if ($this->orbitType === self::ORBIT_TYPE_ELLIPT) {
                $this->orbitTypeObj = new EllipticalOrbit() ;
                $this->orbitTypeObj->setting($this->orbitPar,$this->ellipsePar) ;
            } else {
                $this->orbitTypeObj = new CircularOrbit() ;
                $this->orbitTypeObj->setting($this->orbitPar) ;
            }
        return $this ;
    }
    public function getPar() {
        return $this->sourcePar ;
    }
    /**
     * расчёт параметров
     * исходные данные сохраняются в $sourcePar
     * для расчётов делятся на параметры периода и параметры орбиты
     */
    private function clcParms()
    {
//        $oPar = false ;
//        if (!isset($this->parObjects[$this->planetId])) {
//            if ($this->planetId === self::OBJECT_ID_EARTH) {
//                $oPar = new EarthPar();
////                $this->parObjects[$this->planetId] = new EarthPar();
//            } else {
//                $oPar = new MoonPar(); ;
////                $this->parObjects[$this->planetId] = new MoonPar();
//            }
//            $this->parObjects[$this->planetId] = $oPar ;
//        }
        $oPar = $this->parObjects[$this->planetId];
//        $oPar = new EarthPar();
        $tTest = $this->dateT;    // тестовый момент времени
        $perPar = $oPar->getParClc('getPeriod', $tTest);
        $ellipsePar = $oPar->getParClc('getEllipsePar', $tTest);
        $peri = $ellipsePar['peri'];
        $apo = $ellipsePar['apo'];
        $this->sourcePar = [    // параметры из исходных данных
            'period' => ['dBeg' => $perPar['dBeg'],
                'dEnd' => $perPar['dEnd'],
                'd0' => $perPar['d0'],      // дата - начало отсчёта
                'T' => $perPar['T'],
                'dMiddle' =>  (is_null($perPar['dMiddle'])) ? '' : $perPar['dMiddle'],
                'default' => $perPar['default'],],
            'ellipse' => [
                'peri' => [
                    'dBeg' => $peri['dBeg'],
                    'dEnd' => $peri['dEnd'],
                    'r' => $peri['r']],
                'apo' => [
                    'dBeg' => $apo['dBeg'],
                    'dEnd' => $apo['dEnd'],
                    'r' => $apo['r']],
                'default' => $apo['default']],
        ];
        $this->orbitParClc() ;
        $this->ellipseParClc() ;
    }
    protected function orbitParClc() {
        $period = $this->sourcePar['period'] ;
        $this->orbitPar  = [
            'dBeg' => $period['dBeg'],
            'dEnd'=> $period['dEnd'],
            'd0'=> $period['d0'],     //  начальный момент, в который  theta = 0
            'theta0' => 0.00,  // угол, соотв ts0, - смещение по отношение tsBeg
            // для Солнца - tBeg=tPeri -> thBeg = 0;
            // t0 = T равноденствие -> th0 = угол по отнош перигелию
            // для Луны: t0 - полнолуние
            'T'=>  $period['T'] ,
        ] ;

    }

    protected function ellipseParClc() {
        $ellise = $this->sourcePar['ellipse'] ;
        $rPeri = $ellise['peri']['r'] ;
        $rApo =  $ellise['apo']['r'] ;
        $this->ellipsePar = [
            'rPeri' => $rPeri, // перицентр
            'rApo' => $rApo,  // апоцентр
        ] ;
    }

    /**
     * центральный угол орбиты, соотв моменту $dT
     * @param $dT
     * @param $timestampFlag - true => дата уже в формате timestamp
     * @return float
     */
    public function getTheta($dT = false,$timestampFlag = false,$noDeltaFlag = false) {

        $oTObj = $this->orbitTypeObj ;
        if ($this->orbitType == Common::ORBIT_TYPE_CIRCLE) {
            $theta = $oTObj->getTheta1($dT,$timestampFlag,$noDeltaFlag) ;
//            $theta = $oTObj->getTheta($dT,$timestampFlag,$noDeltaFlag) ;
        } else {
            $theta = $oTObj->getTheta($dT,$timestampFlag,$noDeltaFlag) ;
        }

        return $theta ;
    }
    public function getTs($theta) {
        $oTObj = $this->orbitTypeObj ;
        $ts = $oTObj->getTs($theta) ;
        return $ts ;
    }
    /**
     * контрольные точки (нужны только для круговой орбиты(Луны))
     * @param $ts
     * @param $theta
     * @return $this
     */
    public function setControlPoint($ts,$theta) {
        $oTObj = $this->orbitTypeObj ;
        if ($this->orbitType == Common::ORBIT_TYPE_CIRCLE) {
            $oTObj->setControlPoint($ts,$theta) ;
        }
        return $this ;
    }

}