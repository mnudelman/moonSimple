<?php
/**
 *  Фаза Луны
 *
 */
/**
 * формула расчёта
 * https://astronomy.ru/forum/index.php/topic,100230.0.html
 * Ф = cos**2(lambda/2)
 * lambda = (2pi) * t/29.53
 * Ф - фаза, t - возраст. Лямбда - элонгация. В первом приближении.
 * эта же формула в https://abakbot.ru/online-6/141-moon
 *  Фаза планеты измеряется отношением площади освещенной части видимого диска
 * ко всей его площади. (наконец-то определение!)
 * Угол между направлением с планеты на Солнце и Землю называется фазовым углом.
 *  При фазовом угле ф = 180° (планета находится между Солнцем и Землей) фаза равна нулю,
 * так как половина планеты, обращенная к Земле, не освещена совсем
 * (Для Луны этот момент называется новолунием).
 *При фазовом угле ф = 0 (Земля и Солнце находятся по одну сторону от планеты)
 * фаза равна 1, видимый диск планеты освещен полностью
 * (Для Луны этот момент называется полнолунием).
 * В общем случае связь между фазой Ф и фазовым углом ф определяется формулой
 * Ф = cos**2(f/2)
Связь фазового угла и фазы

Фазовый угол для нижней планеты изменяется от 0° (верхнее соединение) до 180° (нижнее соединение) и, следовательно, ее фазы изменяются от нуля до единицы

 *
 */
use Solaris\MoonPhase1 ;

class MoonPhaseReport extends Report
{
    private $objectTab = [] ;    // географические координаты объектов
    private $dateTab = [] ;      // список дат для отчёта
    private $currentKey ;        // текущий ключ для выбора объекта
    private $udtObj = null ;     // ссылка на объект UpDownTuning
    private $aPoZObj = null;     // объект AnglePoZPhi - азимут восхода/заката
    private $moonOrbitType  ;    // - тип лунной орбиты
    private $earth = ['id' => 0, 'orbit' => 0] ;
    private $moon  = ['id' => 0, 'orbit' => 0] ;
    private $eOrbitObj ;       // объект орбита Земли
    private $mOrbitObj ;       // объект орбита Луны
    private $moonMonth = [     // текущий лунный месяц
        'dBeg' => '',          // дата начала
        'dEnd' => '',          // дата окончания
        'T'    => 0.,          // период
    ] ;
    private $moonPhase1Obj ;   // объект класса MoonPhase1
    //---------------------------------------------//
    public function __construct() {
        $this->setDates() ;
        $this->setObjects() ;
        $this->udtObj = new UpDownTuning() ;
        $this->aPoZObj = new AnglePoZPphi() ;

        $this->moonPhase1Obj = new MoonPhase1() ;

        $this->eOrbitObj = (new Orbit())
            ->setPlanetId(Common::OBJECT_ID_EARTH)
            ->setOrbitType(Common::ORBIT_TYPE_ELLIPT) ;
        $this->moonOrbitType = Common::ORBIT_TYPE_CIRCLE ;
//        $this->moonOrbitType = Common::ORBIT_TYPE_ELLIPT ;
        $this->mOrbitObj = (new Orbit())
            ->setPlanetId(Common::OBJECT_ID_MOON)
            ->setOrbitType($this->moonOrbitType ) ;
        $this->capIni() ;
    }
    public function addNewKey($key,$name,$lat,$long) {
        $this->objectTab[$key] = [
            'name' => $name,
            'lat' => $lat,
            'long' => $long,
        ] ;
        $this->currentKey = $key ;
    }

    public function reportDo($key = false) {
        $key = (false === $key) ? $this->currentKey : $key ;
        $this->currentKey = $key ;

//        $this->titleIni() ;
//        $this->begTab();
        $mOrbitObj = $this->mOrbitObj ;      // орбита Луны
        $eOrbitObj = $this->eOrbitObj ;      // орбита Земли
        $parTun = ['deltaSec' => 0] ;
        $mOrbitObj->setParTuning($parTun) ;
        for ($i = 0; $i < sizeof($this->dateTab); $i++) {
            $date = $this->dateTab[$i] ;
            $rMoonPar = $mOrbitObj->setTestDT($date)
                ->getPar() ;
            $rMoonPer = $rMoonPar['period'] ;
            $newMoonDate = $rMoonPer['d0'] ;   // дата новолуния
            $this->moonMonth['dBeg'] = $rMoonPer['dBeg'] ;    // дата новолуния
            $this->moonMonth['dEnd'] = $rMoonPer['dEnd'] ;    // дата след новолуния
            $this->moonMonth['T'] =  $rMoonPer['T'] ;    // период (дней)
            $this->moonMonth['dMiddle'] = $rMoonPer['dMiddle'] ;
            $thetaMoon0 = $eOrbitObj->setTestDT($date)
                ->getTheta($newMoonDate) ;
            $tsMoonBeg = strtotime($rMoonPer['dBeg']) ;    // дата новолуния
            $tsMoonEnd = strtotime($rMoonPer['dEnd']) ;    // дата след новолуния
            $tsMoon = $tsMoonBeg ;
            $dTs = 24 * 3600 ;
// запихиваем контрольные точки
            $ts = strtotime($rMoonPer['dBeg']) ;
            $theta = $eOrbitObj->getTheta($ts,true)  - $thetaMoon0;
            $mOrbitObj->setControlPoint($ts,$theta) ;
            $ts = strtotime($rMoonPer['dMiddle']) ;
            $theta = $eOrbitObj->getTheta($ts,true)  - $thetaMoon0 ;
            $mOrbitObj->setControlPoint($ts,  $theta + pi()) ;
            $ts = strtotime($rMoonPer['dEnd']) ;
            $theta = $eOrbitObj->getTheta($ts,true)  - $thetaMoon0  ;
            $mOrbitObj->setControlPoint($ts,  $theta + 2*pi()) ;


            $specPoints = [
                'dBeg' => ['date' => $this->moonMonth['dBeg'],
                    'ts' => strtotime($this->moonMonth['dBeg']),
                    'tF' => $this->decomposeDate($this->moonMonth['dBeg'])],
                'dMiddle' => ['date' => $this->moonMonth['dMiddle'],
                    'ts' => strtotime($this->moonMonth['dMiddle']),
                    'tF' => $this->decomposeDate($this->moonMonth['dMiddle'])],
                'dEnd' => ['date' => $this->moonMonth['dEnd'],
                    'ts' => strtotime($this->moonMonth['dEnd']),
                    'tF' => $this->decomposeDate($this->moonMonth['dEnd'])],
            ] ;

            $this->titleIni() ;
            $this->begTab();
            $dayNumber = 0 ;
            $dayMax = 33 ;
            $moonPhase = $this->moonPhase1Obj ; // тестовые данные из MoonPhase1
//            while ($tsMoon  <= $tsMoonEnd || $dayNumber < $dayMax ) {
             while ($tsMoon  <= $tsMoonEnd) {

                $tMF = $this->decomposeDate($tsMoon,true) ;
                $specPointFlag = false ;
                foreach ($specPoints as $key => $value) {
                    $tF = $value['tF'] ;
                    if ($tF['y'] === $tMF['y'] && $tF['m'] === $tMF['m'] &&
                        $tF['d'] === $tMF['d'] ) {
                        $tsMoon = $value['ts'] ;
                        $specPointFlag = true ;
                        break ;
                    }
                }


                $thetaE = $eOrbitObj->getTheta($tsMoon,true) - $thetaMoon0 ;
                $thetaM = $mOrbitObj->getTheta($tsMoon,true) ;
                $kFit = 1 ; // 32/30  ; // - подгонка для выхода на 0 в след новолуние
                $phi =  $kFit * ($thetaE - $thetaM) ;
                $phiRad = $phi / pi() * 180 ;
                $cosPhi = cos($phi) ;
                $iClc = 1/2 * (1 - $cosPhi) ;
                $d = $this->decomposeDate($tsMoon,true) ;
                $date = $d['y'] . '-' . $d['m'] . '-' . $d['d'] . ' ' .
                    $d['h'] . ':' . $d['i'] . ':' . $d['s'] ;
//                * Ф = cos**2(lambda/2)
//                * lambda = (2pi) * t/29.53
                  $Tsec = 29.53 * 24 * 3600 ;
                  $lambda =  pi() * ($tsMoon - $tsMoonBeg) /$Tsec ;
                  $Phi =  cos($lambda) ** 2 ;
// тестовые данные из MoonPhase1
                 $testData = $moonPhase->setTs($tsMoon)
                 ->getResult() ;
                $r = [
                    'dayNumber' => $dayNumber,
                    'tsMoon' => $tsMoon,
                    'date' => $date,
                    'thetaE' => $thetaE,
                    'thetaEGrad' => round($thetaE / pi() * 180,2),
                    'thetaM' => $thetaM,
                    'thetaMGrad' => round($thetaM / pi() * 180,2),
                    'phi' => $phi,
                    '$phiRad' => $phiRad,
                    'cosPhi' => $cosPhi,
                    'i' => round($iClc,6),
                    'formulaPhi' => $Phi,
                    'i-Phi' => round(1 - $Phi,6),
                    'test' => [
                        'illumination' => round($testData['illumination'],2),
                        'age' => round($testData['age'],2),
                    ]
                ] ;
//                var_dump($r);
                if ($tsMoon < $tsMoonEnd && $tsMoon + $dTs > $tsMoonEnd  ) {
                    $tsMoon = $tsMoonEnd ;
                } else {
                    $tsMoon += $dTs ;
                }

                $this->makeRow($r,$specPointFlag) ;

                $dayNumber++ ;
            }

//            var_dump($rMoonPar);
//

        }
        $this->endTab();


    }

    /**
     * @param $r = [
    'dayNumber' => $dayNumber,
    'tsMoon' => $tsMoon,
    'date' => $date,
    'thetaE' => $thetaE,
    'thetaM' => $thetaM,
    'thetaMGrad' => $thetaM / pi() * 180,
    'phi' => $phi,
    '$phiRad' => $phiRad,
    'cosPhi' => $cosPhi,
    'i' => $i,
    'formulaPhi' => $Phi,
    'i-Phi' => 1 - $Phi,
    ] ;
     *             'date',                 // дата
    'thetaM-Grad',    // угол -положение плоскости PmoonFase относительно
    //         новолуния
    'i',              // освещённость диска
    'i-formula',      // освещённость по формуле

     */
    private function makeRow($r,$specPointFlag = false) {
        $tagBeg = '' ;
        $tagEnd = '' ;
        if ($specPointFlag) {
            $tagBeg = '<b>' ;
            $tagEnd = '</b>' ;
        }
        $this->setCell('day-number',$tagBeg. $r['dayNumber'] . $tagEnd) ;
        $this->setCell('date',$tagBeg . $r['date']) ;
        $this->setCell('thetaM-Grad',$tagBeg . $r['thetaMGrad'] . $tagEnd) ;
//        $this->setCell('thetaE-Grad',$tagBeg . $r['thetaEGrad'] . $tagEnd) ;
        $this->setCell('i',$tagBeg . $r['i'] . $tagEnd) ;
        $this->setCell('i-formula',$tagBeg . $r['i-Phi'] . $tagEnd) ;
        $testData = $r['test'] ;
        $this->setCell('test-i',$testData['illumination']) ;
        $this->setCell('test-age',$testData['age']) ;
        $this->rowOut();
    }
    private function titleIni() {
        $key = $this->currentKey ;
        $town = $this->objectTab[$key]['name'] ;
        $lat =  $this->objectTab[$key]['lat'] ;
        $long =  $this->objectTab[$key]['long'] ;
        $moonOrbitName = ($this->moonOrbitType === Common::ORBIT_TYPE_CIRCLE) ?
            'круговая' : 'эллиптическая' ;
        $title = '<b>Лунная фаза в течении лунного месяца</b><br>' .
        '<b>' . 'начало(новолуние) : ' . '</b>' . $this->moonMonth['dBeg'] . '<br>' .
        '<b>' . 'конец: ' . '</b>' . $this->moonMonth['dEnd'] . '<br>' .
        '<b>' . 'продоложительность: ' . '</b>' . $this->moonMonth['T'] . '<br>' .
        '<b>' . 'полнолуние: ' . '</b>' . $this->moonMonth['dMiddle'] . '<br>' .
        '<b>' . 'тип лунной орбиты: ' . '</b>' . $moonOrbitName . '<br>' ;
        $this->setTitle($title) ;
    }
    private function capIni() {
        $cap = [
            'day-number',     // день от новолуния
            'date',                 // дата
            'thetaM-Grad',    // угол -положение плоскости PmoonFase относительно
                              //         новолуния
//            'thetaE-Grad',    // угол -положение плоскости Pdl
            'i',              // освещённость диска
            'i-formula',      // освещённость по формуле
            'test-i',         // тестовые данные по освещённости
            'test-age',       // дней от новолуния
        ] ;
        $this->setCap($cap) ;
    }
    private function setDates() {
        $this->dateTab = [
// - новолуние '2020-02-24 15:34',
//                '2020-02-25',      // 1
 //            '2020-02-26',      // 2
//            '2020-02-27',         // 3
//            '2020-02-28',         // 4
//            '2020-02-29',         // 5
//            '2020-03-1',         // 6
//            '2020-03-2',          //7
//            '2020-03-3',          //8
//            '2020-03-4',          //9
//            '2020-03-06',          //11
//            '2020-03-08',          // 13
//            '2020-03-10',          // 15
//            '2020-03-12',          // 17
//            '2020-03-14',          // 19
//            '2020-03-16',          // 21
//            '2020-03-18',          // 23
//            '2020-03-20',          // 25
//            '2020-03-22',          // 27
//            '2020-03-23',          // 28
//            '2020-03-24',          // 29
            '2020-09-15',
        ] ;
    }
    private function setObjects() {
        $this->objectTab = [
            'GMT' => ['name' => 'Greenwich',
                'lat' => 51.507351,      // широта51.507351, -0.127660
                'long' => 0],    // долгота
            'LON' => ['name' => 'London',
                'lat' => 51.507351,      // широта51.507351, -0.127660
                'long' => -0.127660],    // долгота

            'OREN' => ['name' => 'Orenburg',
                'lat' => 51.768199,      // широта,
                'long' => 55.096955],    // долгота
            'EBURG' => ['name' => 'Ekaterinburg',
                'lat' => 56.838011,      // широта,
                'long' => 60.597465],    // долгота
            'MSC' => ['name' => 'Moscow',
                'lat' => 55.755814,      // широта,
                'long' => 37.617635],    // долгота
            'PETER' => ['name' => 'Petersburg',
                'lat' => 59.939095,      // широта,
                'long' => 30.315868],    // долгота
            'JER' => ['name' => 'Jerusalem',
                'lat' => 31.777493,      // широта,
                'long' => 35.205165],    // долгота
            'ARKH' => ['name' => 'Arkhangelsk',
                'lat' => 64.539911,      // широта,
                'long' => 40.515753],    // долгота
            'MURM' => ['name' => 'Murmansk',
                'lat' => 68.970682,      // широта,
                'long' => 33.074981],    // долгота

        ] ;
    }

}