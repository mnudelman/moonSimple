<?php
/**
 * Class MoonPhaseSimple - расчёт лунной фазы, вернее это расчёт того, что называется
 * illumination - освещённость (доля лунного диска освещённая Солнцем)
 * название MoonPhase оставил за Solaris\MoonPhase
 */
use Solaris\MoonPhase1 ;     // тестовые расчёты
//use MoonControlPoints ;
//use Solaris\MoonPhase1 ;
class MoonPhaseSimple extends Common
{
    private $currentDate ;
    private $currentTs;
    private $udtObj = null ;     // ссылка на объект UpDownTuning
    private $aPoZObj = null;     // объект AnglePoZPhi - азимут восхода/заката
    private $moonOrbitType  ;    // - тип лунной орбиты
    private $eOrbitObj ;       // объект орбита Земли
    private $mOrbitObj ;       // объект орбита Луны
    private $moonPhase1Obj ;   // объект класса MoonPhase1
    private $controlPoints ;   // контрольные точки Лунной орбиты(новолуние,полнолуние,nextNewmoon)
    private $cntPObj ;
    //---------------------------------------------//
    public function __construct() {
//        $this->udtObj = new UpDownTuning() ;
//        $this->aPoZObj = new AnglePoZPphi() ;

        $this->moonPhase1Obj = new MoonPhase1() ;

        $this->eOrbitObj = (new Orbit())
            ->setPlanetId(Common::OBJECT_ID_EARTH)
            ->setOrbitType(Common::ORBIT_TYPE_ELLIPT) ;
        $this->moonOrbitType = Common::ORBIT_TYPE_CIRCLE ;
//        $this->moonOrbitType = Common::ORBIT_TYPE_ELLIPT ;
        $parTun = ['deltaSec' => 0] ;   // подстройка даты начала новолуния
        $this->mOrbitObj = (new Orbit())
            ->setPlanetId(Common::OBJECT_ID_MOON)
            ->setOrbitType($this->moonOrbitType )
            ->setParTuning($parTun) ;
        $mOrbitObj = $this->mOrbitObj ;      // орбита Луны
        $eOrbitObj = $this->eOrbitObj ;      // орбита Земли

        $this->cntPObj = (new MoonControlPoints())
            ->setMoonOrbit($mOrbitObj)
            ->setEarthOrbit($eOrbitObj) ;

    }

    /**
     * текущая дата - единственный паракметр, требуемый для расчёта
     * @param $dt
     * @param bool $tsFlag
     * @return $this
     */
    public function setDate($dt,$tsFlag = false) {
        if (!$tsFlag) {
            $this->currentDate = $dt ;
            $this->currentTs = strtotime($dt) ;
        }else {
            $this->currentTs = $dt ;
            $dF = $this->decomposeDate($dt,$tsFlag) ;
            $this->currentDate = $dF['y'] . '-' . $dF['m'] . '-' . $dF['d'] . ' ' .
                $dF['h'] . ':' . $dF['i'] . ':' . $dF['s'] ;
        }
        $mOrbitObj = $this->mOrbitObj ;      // орбита Луны
        $eOrbitObj = $this->eOrbitObj ;      // орбита Земли
//        $moonPhase = $this->moonPhase1Obj ;  // тестовый расчёт
        $this->controlPoints =   // контрольные точки орбиты Луны
            ($this->cntPObj)
                ->setDt($this->currentDate)
                ->pointsGo() ;

        return $this ;
    }
    public function getControlPoints() {
        return $this->controlPoints ;
    }
    public function phaseDo() {
        $mOrbitObj = $this->mOrbitObj ;      // орбита Луны
        $eOrbitObj = $this->eOrbitObj ;      // орбита Земли
        $moonPhase = $this->moonPhase1Obj ;  // тестовый расчёт
        $controlPoints = $this->controlPoints ;

        $tsMoonBeg = $controlPoints['dBeg']['ts'] ;
        $tsMoon = $this->currentTs ;
        $dayNumber = floor(($tsMoon - $tsMoonBeg)/(24*3600)) + 1 ;

        $newMoonDate = $controlPoints['dBeg']['date'] ;
        $endMoonDate = $controlPoints['dEnd']['date'] ;
//        угол полскости Pdl в момент новолуния
        $thetaMoon0 = $eOrbitObj->setTestDT($this->currentDate)
            ->getTheta($newMoonDate) ;
        $tsMoonBeg = strtotime($newMoonDate) ;    // дата новолуния

        $thetaE = $eOrbitObj->getTheta($tsMoon,true) - $thetaMoon0 ;
        $thetaM = $mOrbitObj->getTheta($tsMoon,true) ;
        $phi = $thetaE - $thetaM ;
        $cosPhi = cos($phi) ;
        $iClc = 1/2 * (1 - $cosPhi) ;

        $d = $this->decomposeDate($tsMoon,true) ;
        $date = $d['y'] . '-' . $d['m'] . '-' . $d['d'] . ' ' .
            $d['h'] . ':' . $d['i'] . ':' . $d['s'] ;
// расчёт по формуле:
//                * Ф = cos**2(lambda/2)
//                * lambda = (2pi) * t/29.53
        $Tsec = 29.53 * 24 * 3600 ;
        $lambda =  pi() * ($tsMoon - $tsMoonBeg) /$Tsec ;
        $Phi =  cos($lambda) ** 2 ;
// тестовые данные из MoonPhase1
        $testData = $moonPhase->setTs($tsMoon)
            ->getResult() ;
//-----------------------------
        return [
            'dayNumber' => $dayNumber,
            'tsMoon' => $tsMoon,
            'date' => $date,
            'thetaE' => $thetaE,
            'thetaEGrad' => rad2deg($thetaE),
            'thetaM' => $thetaM,
            'thetaMGrad' => rad2deg($thetaM),
            'phi' => $phi,
            'cosPhi' => $cosPhi,
            'phiDeg' => deg2rad($phi),
            'phiDegTest' => $testData['ageAngleDeg'],
            'i' => round($iClc,6),
            'formulaPhi' => $Phi,
            'i-Phi' => round(1 - $Phi,6),
            'test' => [
                'illumination' => round($testData['illumination'],2),
                'age' => round($testData['age'],2),
            ],
        ] ;
    }

}