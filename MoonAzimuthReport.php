<?php
/**
 * Class MoonAzimuthReport - отчёт о высоте и азимуте
 */

class MoonAzimuthReport extends Report
{
    private $objectTab = [] ;        // географические координаты объектов
    private $dateTab = [] ;          // список дат для отчёта
    private $currentKey ;            // текущий ключ для выбора объекта
    private $udtObj = null ;         // объект UpDownTuning - расчёт точек восхода/заката
    private $aPoZObj = null;         // объект AnglePoZPhi - азимут в точках восхода/заката
    private $cpObj = null ;          // объект CyclePoints - точки расчёта
    private $haObj = null ;          // объект HeightAndAzimuth - азимут и высота над горизонтом
    private $upDnMbObj ;             // UpDnMontenbruck - алгоритмы от Монтенбрук ...
    private $orbitObjM = null ;      // орбита Луны
    private $orbitObjE = null ;      // орбита Земли
    private $planetId ; //  - ид планеты
    private $orbitType  ; // - тип орбиты
    private $moonTheta0 ;

    //---------------------------------------------//
    public function __construct() {
        $this->setDates() ;
        $this->setObjects() ;
        $this->upDnMbObj = new UpDnMontenbruck() ;
        $this->udtObj = new UpDownTuning() ;      // вычисление восхода/заката
        $this->aPoZObj = new AnglePoZPphi() ;  // азимут восхода/заката
        $this->cpObj = new CyclePoints();          // расчётные точки
        $this->haObj = new HeightAndAzimuth() ;  //  азимут и высота над горизонтом
        $this->planetId = Common::OBJECT_ID_MOON; //  - ид планеты
        $this->orbitType = Common::ORBIT_TYPE_CIRCLE ; // - тип орбиты
//        $this->orbitType = Common::ORBIT_TYPE_ELLIPT ; // - тип орбиты
        $this->orbitObjM = (new Orbit())   // потребуется для вычисление  theta
        ->setOrbitType($this->orbitType)   //тип орбиты (круговая|эллиптическая
        ->setPlanetId($this->planetId) ;   //- ид планеты (Земля|Луна)

        $this->orbitObjE = (new Orbit())   // потребуется для вычисление  theta
        ->setOrbitType(Common::ORBIT_TYPE_ELLIPT)   //тип орбиты (круговая|эллиптическая
        ->setPlanetId(Common::OBJECT_ID_EARTH) ;   //- ид планеты (Земля|Луна)



        $this->capIni() ;
    }
    public function addNewKey($key,$name,$lat,$long) {
        $this->objectTab[$key] = [
            'name' => $name,
            'lat' => $lat,
            'long' => $long,
        ] ;
        $this->currentKey = $key ;
        return $this ;
    }
    public function addNewDate($dt) {
        $this->dateTab[0] = $dt ;
        return $this ;
    }
    public function reportDo($key = false)
    {
       // подстраиваем под date0
        $date0 = $this->dateTab[0] ;
        $orbitObjE = $this->orbitObjE ;
        $orbitObjM = $this->orbitObjM ;
        $orbitObjE->setTestDT($date0);
        $orbitObjM->setTestDT($date0);

        $rMoonPar = $orbitObjM->getPar() ;
        $rMoonPer = $rMoonPar['period'] ;
        $newMoonDate = $rMoonPer['d0'] ;   // дата новолуния
        $thetaMoon0 = $orbitObjE->getTheta($newMoonDate) ;
// запихиваем контрольные точки
        $ts = strtotime($rMoonPer['dBeg']) ;
        $theta = $orbitObjE->getTheta($ts,true)  - $thetaMoon0;
        $orbitObjM->setControlPoint($ts,$theta) ;
        $ts = strtotime($rMoonPer['dMiddle']) ;
        $theta = $orbitObjE->getTheta($ts,true)  - $thetaMoon0 ;
        $orbitObjM->setControlPoint($ts,$theta + pi()) ;
        $ts = strtotime($rMoonPer['dEnd']) ;
        $theta = $orbitObjE->getTheta($ts,true)  - $thetaMoon0  ;
        $orbitObjM->setControlPoint($ts,$theta + 2*pi()) ;
//-----------------------------------



        $key = (false === $key) ? $this->currentKey : $key;
        $this->currentKey = $key;
        $this->titleIni();
        $this->begTab();
//    тестовые данные из    $this->upDnMbObj
        $upDnMbObj = $this->upDnMbObj ;
        $lat = $this->objectTab[$this->currentKey]['lat'] ;
        $long = $this->objectTab[$this->currentKey]['long'] ;
        $upDnMbObj->setPoint($lat, $long)
            ->setObjectId(Common::OBJECT_ID_MOON);
//



        $r = $this->upDownClc() ;      // точки восхода/заката
//      -----------------------------------------------------------    //
        $date0 = $r['date0'] ;   // дата расчёта
        $this->cyclePointsClc($r,$newMoonDate) ;     // подготовить параметры цикла
        $theta0 = $r['point0']['theta'] ;    // пложение Pdl для начала суток
        $moonTheta0 = $r['moonTheta0'] ;     // Pdl для новолуния
        $this->moonTheta0 = $moonTheta0 ;




        $latitudeGrad = $this->objectTab[$this->currentKey]['lat'] ;
        $this->cycleDo($theta0,$date0,$latitudeGrad,$moonTheta0) ;
        $this->tabPrint() ;
        $this->endTab();


    }
    private function tabPrint() {
        $cpO = $this->cpObj ;
        $cpO->setIndexTop() ;
        while (($r = $cpO->getNext()) !== false) {
            $this->makeRow($r) ;
        }
    }
    /**
     * перебор точек расчёта
     * @param $theta0 - начальное положение плоскости Pdl на начало суток
     */
    private function cycleDo($theta0,$date0,$latitudeGrad,$moonTheta0) {
       $haO = $this->haObj ;     // объект HeightAndAzimuth

        $haO->setTheta($theta0)
        ->setLatitude($latitudeGrad) ;      // широта
        $upDnMbObj = $this->upDnMbObj ;
//        $lat = $this->objectTab[$this->currentKey]['lat'] ;
//        $long = $this->objectTab[$this->currentKey]['long'] ;
//        $upDnMbObj->setPoint($lat, $long)
//            ->setObjectId(Common::OBJECT_ID_MOON);




        $currentDayTime = false ;
        $cpO = $this->cpObj ;
        $cpO->setIndexTop() ;
        while (($r = $cpO->getNext()) !== false) {

            $ts = $r['ts'] ;
            $psi = $r['psi'] ;
            $type = $r['type'] ;
            $dayTime = $r['dayTime'] ;
            if (!is_null($dayTime)) {
                if ($dayTime !== $currentDayTime) {
                    $currentDayTime = $dayTime ;
                    $haO->setDayTimeType($dayTime) ;
                }
            }
// тестовые данные
            $mbObj = $this->upDnMbObj ;
            $r = $mbObj->azHClc($ts) ;
            $azTest = $r['az'] ;
            $hTest =  $r['h'] ;
            $cpO->setAttribute('az-test',$azTest)  ;
            $cpO->setAttribute('h-test',$hTest)  ;
//-----------------
            switch ($type) {
                case 'p':        // простая точка расчёта
                    $this->heightAzimuthDo($psi,$dayTime,$ts) ;
                    break ;
                case 'pUp':      // восход
                    $cpO->setAttribute('height',0) ;
//                    $cpO->setAttribute('az-test',$azTest) ;
//                    $cpO->setAttribute('h-test',$hTest) ;
                    break ;
                case 'pDown':    // закат
                    $cpO->setAttribute('height',0) ;
//                    $cpO->setAttribute('az-test',$azTest) ;
//                    $cpO->setAttribute('h-test',$hTest) ;
                    break ;
                case 'p0' :      // полночь
                    $this->heightAzimuthDo($psi,$dayTime,$ts) ;
                    break ;
                case 'p1' :      // начало (x=1,y=0)
                    $this->heightAzimuthDo($psi,$dayTime,$ts) ;
                    break ;
                case 'pTheta' :  // поворот Pdl по текущенму времени ts
                    $theta = $this->getTheta($ts) ;
                    $haO->setTheta($theta + $moonTheta0) ;

                    break ;
            }
        }
//        $cpO->dumpPoints() ;
    }

    /**
     * перевычисление theta - положение Pdl
     */
    private function getTheta($ts) {

        $orbirObj = $this->orbitObjM ;
        $theta = $orbirObj->getTheta($ts,true); // - центральный угол точки орбиты
        return $theta ;

    }
    private function heightAzimuthDo($psi,$dayTime,$ts) {
        $haO = $this->haObj ;     // объект HeightAndAzimuth
        $cpO = $this->cpObj ;
        $psiGrad = rad2deg($psi) ;
        $r = $haO->setBPointByAngle($psiGrad)
            ->getSunCoordinate() ;
        $h = $r['grad']['h'] ;
        $a = $r['grad']['aTuning'] ;
        $h = ($dayTime === Common::DAY_TIME_DARK) ? -$h : $h ;
        $cpO->setAttribute('height',$h)     // сохранить значения
        ->setAttribute('azimuth',$a) ;
        $a = $haO->angleCrescentMoon() ;
//        $aGrad = round(rad2deg($a['angle']),2) . ' : ' . $a['vp'] .
//        ':' .$a['nTheta'];
        $aGrad = round(rad2deg($a['angle']),3) ;
        $cpO->setAttribute('crescent',$aGrad)  ;
// тестовые данные
        $mbObj = $this->upDnMbObj ;
        $r = $mbObj->azHClc($ts) ;
        $cpO->setAttribute('az-test',$r['az'])  ;
        $cpO->setAttribute('h-test',$r['h'])  ;
    }
    /**
     * для выбора параметров цикла
     */
    private function getCycleAttributes() {
        $cycleUnitAngle = CyclePoints::CYCLE_UNIT_ANGLE ;
        $cycleUnitTime = CyclePoints::CYCLE_UNIT_TIME;
        return [
            'cycleUnit' => $cycleUnitTime,
            'beg' => '00:01:00',
            'end' => '23:59:00',
            'nSteps' => 50,
        ] ;
    }
    /**
     * настройка объекта CyclePoints
     * всё остаётся в объекте $this->cpObj
     */
    private function cyclePointsClc($rUpDown,$newmoonDate) {
        $cpO = $this->cpObj ;
        $point0 = $rUpDown['point0'];         // полночь
        $up = $rUpDown['up'];                 // восход
        $upTimeFormat = $up['timeCorrFormat'];
        $upTheta = $up['theta'] ;
        $upPsiGrad = $up['psiGrad'] ;

        $down = $rUpDown['down'];
        $downTimeFormat = $down['timeCorrFormat'];
        $downTheta = $down['theta'] ;
        $downPsiGrad = $down['psiGrad'] ;

        $aUp = $rUpDown['azimuth']['up'] ;     // азимут - восход
        $aDn = $rUpDown['azimuth']['dn'] ;     // азимут - закат
        $date = $this->dateTab[0] ;    // берём единственное значение
//-------------------------------------------------------------
// корректировка времени восхода/заката по Монтенбрук...
        $upDnMbObj = $this->upDnMbObj ;
//        $lat = $this->objectTab[$this->currentKey]['lat'] ;
//        $long = $this->objectTab[$this->currentKey]['long'] ;
        $ts = strtotime($date) ;
//        $upDnMbObj->setPoint($lat, $long) ;
        $r = $upDnMbObj->setTime($ts, true)
                ->upDownClc();
        $upTest = ($r[0]['type'] === 'up') ? $r[0] : false ;
        $upTest = ($r[1]['type'] === 'up') ? $r[1] : $upTest ;
        $dnTest = ($r[0]['type'] === 'dn') ? $r[0] : false ;
        $dnTest = ($r[1]['type'] === 'dn') ? $r[1] : $dnTest ;
        $upTimeFormat = $this->decomposeDate($upTest['dt']) ;
        $downTimeFormat = $this->decomposeDate($dnTest['dt']) ;
// ----------------------------------------------------
        $psi0EarthGrad = rad2deg($point0['psiEarth']) ;
        $psi0Grad = rad2deg($point0['psi']) ;
//        if ($psi0Grad < $psi0EarthGrad ) {
//            $psi0Grad += 360 ;
//        }
        $dts = abs($psi0EarthGrad - $psi0Grad) * Common::MINUTES_IN_DEGREE * 60 ;
        $ts = strtotime($date) + $dts ;
        echo '$psi0Grad: ' . $psi0Grad .'<br>' ;
        echo '$psi0EarthGrad: ' . $psi0EarthGrad .'<br>' ;
        $psi0 = $point0['psi'] ;
//        $psi0 = $point0['psiEarth'] ;
         $cycleAttr = $this->getCycleAttributes() ;     // назначенные атрибуты
// -----------------------------------------------------
        $cpO->setCurrentDate($date)           // текущая дата
        ->setPoint0($psi0)                          // точка полночь
        ->setUpPoint( $upTimeFormat,$aUp,$upTheta,$upPsiGrad)    // восход
        ->setDownPoint($downTimeFormat,$aDn,$downTheta,$downPsiGrad) // закат
//        ->setPsiInterval($psiInterval)       // угловые интервалы восход-закат и закат-восход
        ->setThetaTimes(['00:00:00','2:00:00','5:00:00',
            '6:00:00','8:00:00','10:00:00','12:00:00','14:00:00',
            '16:00:00','17:45:00','20:00:00','22:00:00',])        // моменты корректировки положения Pdl
//        ->setThetaTimes(['00:00:00','5:00:00','10:00:00','15:00:00','20:00:00',])        // моменты корректировки положения Pdl
//        ->setThetaTimes(['00:00:00',])        // моменты корректировки положения Pdl
        ->setCycle($cycleAttr['cycleUnit'],
            $cycleAttr['beg'], $cycleAttr['end'],
            $cycleAttr['nSteps']);      // границы цикла в единицах времени или угла

    }
    /**
     * Вычислить точки восхода/заката
     */
    private function upDownClc() {
        $key = $this->currentKey ;
        $latitude = $this->objectTab[$key]['lat'];
        $longitude = $this->objectTab[$key]['long'];
        $udt = $this->udtObj;
        $date = $this->dateTab[0];    // берём единственное значение








        $udt->setting($date,    //   - календарная дата
            $latitude,      //   - широта
            $longitude,
            $this->planetId, //  - ид планеты
            $this->orbitType); // - тип орбиты


       $r = $udt->tuningDo1();

//        $r =   $udt->tuningDoNew();   // все восходы/закаты
//
        $apoZObj = $this->aPoZObj ;
        $apoZObj->setLatitudeAngle($latitude) ;           // широта

//        var_dump($r['up']);
//        var_dump($r['down']);
$arr = $r['arr'] ;
for ($i = 0 ; $i < sizeof($arr); $i++) {
    if ($i % 2 == 1) {
        continue;
    }
    if ($i + 1 >= sizeof($arr)) {
        break;
    }
    $p1 = $arr[$i]['point'];
    $p1['theta'] = $arr[$i]['theta'];
    $p2 = $arr[$i + 1]['point'];
    $p2['theta'] = $arr[$i + 1]['theta'];

    $tau = $apoZObj->setPoints($p1, $p2)     // точки восхода/заката
    ->angleClc();                           // вычисление азимута точек
    if ($p1['type'] === Common::POINT_TYPE_SUNRISE) {
        $p1['azimuth'] = $tau['azimuth'][0];
        $p2['azimuth'] = $tau['azimuth'][1];
    } else {
        $p2['azimuth'] = $tau['azimuth'][0];
        $p1['azimuth'] = $tau['azimuth'][1];

    }
    $arr[$i]['point']['azimuth'] = $p1['azimuth'] ;
    $arr[$i + 1]['point']['azimuth'] = $p2['azimuth'] ;

    $arr[$i]['point']['psiGrad'] = $arr[$i]['psiGrad'] ;
    $arr[$i + 1]['point']['psiGrad'] = $arr[$i + 1]['psiGrad'] ;

}


        $pDl = $r['pDl'];
        $pDl['p1']['theta'] = $r['up']['theta'];    // положение Pdl в момент восхода
        $pDl['p2']['theta'] = $r['down']['theta'];  // положение Pdl в момент заката
        $tau = ($this->aPoZObj)
            ->setLatitudeAngle($latitude)           // широта
            ->setPoints($pDl['p1'], $pDl['p2'])     // точки восхода/заката
            ->angleClc();                           // вычисление азимута точек
        $r['azimuth']['up'] = $tau['azimuth'][0];;
        $r['azimuth']['dn'] = $tau['azimuth'][1];
        return $r ;

    }
    private function makeRow($r) {
        $ts = $r['ts'];
        $tf = $this->decomposeDate($ts,true) ;
        $time = $tf['h'] . ':' . $tf['i'] . ':' . $tf['s'] ;
        if (!is_null($r['psi'])) {
            $psi = $r['psi'];
            $psiGrad = round(rad2deg($psi),4) ;
        } else {
            $psiGrad = '   - ' ;
        }
        $typePoint = $r['type'];
        $dayTime = $r['dayTime'];
        if (!is_null($r['height'])) {
            $height = round($r['height'],4) ;
        }  else {
            $height = '   -' ;
        }
        if (!is_null($r['azimuth'])) {
            $azimuth = round($r['azimuth'],4) ;
        }  else {
            $azimuth = '   -' ;
        }

        if (!is_null($r['crescent'])) {
            $crescent = $r['crescent'] ;
        }  else {
            $crescent = '   -' ;
        }

        if (!is_null($r['az-test']) &&  !is_null($r['azimuth'])) {
            $azTest = round($r['az-test'],3) ;
            $azDelta = round($azimuth - $azTest,1) ;
        }  else {
            $azTest = '   -' ;
            $azDelta = '   -' ;
        }
        if (!is_null($r['h-test']) &&  !is_null($r['height'])) {
            $hTest = round($r['h-test'],2) ;
            $hDelta = round($hTest - $height,1)  ;
        }  else {
            $hTest = '   -' ;
            $hTest = '   -' ;
        }



        $this->setCell('ts',$ts) ;
        $this->setCell('time',$time) ;
        $this->setCell('angle',$psiGrad) ;
        $this->setCell('type-point',$typePoint) ;
        $this->setCell('dayTime',$dayTime) ;
        $this->setCell('azimuth',$azimuth) ;
        $this->setCell('height',$height) ;

        $this->setCell('crescent',$crescent) ;
        $this->setCell('az-test',$azTest) ;
        $this->setCell('h-test',$hTest) ;
        $this->setCell('az-delta','<b>' . $azDelta . '</b>') ;
        $this->setCell('h-delta','<b>' . $hDelta . '</b>') ;
        $this->rowOut();
    }
    private function titleIni() {
        $key = $this->currentKey ;
        $town = $this->objectTab[$key]['name'] ;
        $lat =  $this->objectTab[$key]['lat'] ;
        $long =  $this->objectTab[$key]['long'] ;
        $date0 = $this->dateTab[0] ;
        $title = '<b>Азимут и высота Луны</b><br>' .
        '<b>город:</b> ' . $town . '<br>' .
        '<b>широта:</b> ' . $lat . '<br>' .
        '<b>долгота:</b> ' . $long . '<br>' .
        '<b>дата:</b> ' . $date0 . '<br>' ;
        $this->setTitle($title) ;
    }
    private function capIni() {
        $cap = [
            'ts',
            'time',         // время
            'angle',        // угол
            'type-point',   // тип точи
            'dayTime',      // светлое/тёмное время
            'azimuth',      // азимут
            'height',       // высота
            'crescent',     // угол наклона
            'az-test',
            'h-test',
            'az-delta',
            'h-delta',
        ] ;
        $this->setCap($cap) ;
    }
    private function setDates() {
        $this->dateTab = [
//            '2019-01-10',
//            '2019-01-12',
//            '2019-01-15',
//            '2019-01-01',
//            '2019-01-03',
//            '2019-01-05',
//            '2019-01-17',
//            '2019-01-20',
//            '2019-02-10',
//            '2019-03-10',
//            '2019-03-20',
//            '2019-03-20 21:58:25',
//            '2019-04-10',
//            '2019-05-10',
//            '2019-06-05',
//            '2019-06-07',
//            '2019-06-10',
//            '2019-06-12',
//            '2019-06-15',
//            '2019-06-17',
//            '2019-06-20',
//            '2019-07-10',
//            '2019-08-10',
//            '2019-09-10',
//            '2019-10-16',
//            '2019-11-10',
//            '2019-12-10',
//        '2020-01-31',
//            '2019-10-30',
//            '2019-11-05',
//            '2019-11-10',
//            '2019-11-15',
//            '2019-11-20',
//            '2019-11-25',
//            '2019-11-26',


//            '2019-11-22',
//            '2019-12-02',
//            '2019-12-10',
//            '2019-12-23',
// - новолуние '2019-01-6 1:30',
//            '2019-01-07',     // 1
//               '2019-01-08',     // 2
//            '2019-01-16',     // 10
//            '2019-01-21',     // 15
//            '2019-01-26',     // 20
//            '2019-02-01',     // 25
//            '2019-02-03',     // 27
// - новолуние '2019-04-5 8:52',
//            '2019-04-06',     // 1
//            '2019-04-07',     // 2
//            '2019-04-15',     // 10
//            '2019-04-20',     // 15
//            '2019-04-25',     // 20
//            '2019-04-30',     // 25
//            '2019-05-02',     // 27
// - новолуние '2019-07-2 19:17',
//            '2019-07-03',     // 1
//            '2019-07-04',     // 2
//            '2019-07-12',     // 10
//            '2019-07-17',     // 15
//            '2019-07-22',     // 20
//            '2019-07-27',     // 25
//            '2019-07-29',     // 27

// - новолуние '2019-09-28 18:28',
//            '2019-09-30',     // 2
//            '2019-10-08',     // 10
//            '2019-10-13',     // 15
//            '2019-10-18',     // 20
//            '2019-10-23',     // 25
//            '2019-10-25',     // 27


// - новолуние '2019-11-26 15:08',

//            '2019-11-28',     // 2
//            '2019-12-06',     // 10
//            '2019-12-11',     // 15
//            '2019-12-16',     // 20
//            '2019-12-21',     // 25
//            '2019-12-23',     // 27
// - новолуние '2020-01-24 21:44',
//                '2020-01-25',      // 1 ----
//            '2020-01-26',      // 2
//            '2020-01-27',         // 3
//            '2020-01-28',         // 4
//            '2020-01-29',         // 5
//            '2020-01-30',         // 6
//            '2020-01-31',         // 7
//            '2020-02-1',          //8
//            '2020-02-2',          //9
//            '2020-02-3',          //10
//            '2020-02-4',          //11
//            '2020-02-5',          //12
//            '2020-02-06',          //13
//            '2020-02-07',          //14
//            '2020-02-08',          // 15
//            '2020-02-10',          // 17
//            '2020-02-12',          // 19
//            '2020-02-14',          // 21
//            '2020-02-16',          // 23
//            '2020-02-18',          // 25
//            '2020-02-20',          // 27
//            '2020-02-22',          // 29
//            '2020-02-23',          // 30
// - новолуние '2020-02-24 15:34',
//            '2020-02-24 20:00',
//                '2020-02-25',      // 1
//            '2020-02-26',      // 2
//            '2020-02-27',         // 3
//            '2020-02-28',         // 4
//            '2020-02-29',         // 5
//            '2020-03-1',         // 6
//            '2020-03-2',          //7
//            '2020-03-3',          //8
//            '2020-03-4',          //9
//            '2020-03-5',          //10
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
// - новолуние '2020-04-23 2:27',
//            '2020-04-24',          // 1
//            '2020-04-25',      // 2
//            '2020-04-26',         // 3
//            '2020-04-27',         // 4
//            '2020-04-28',         // 5
//            '2020-04-29',         // 6
//            '2020-04-30',          //7
//            '2020-05-1',          //8
//            '2020-05-2',          //9
//            '2020-05-4',          //11
//            '2020-05-6',          // 13
//            '2020-05-8',          // 15
//            '2020-05-10',          // 17
//            '2020-05-12',          // 19
//            '2020-05-14',          // 21
//            '2020-05-16',          // 23
//            '2020-05-18',          // 25
//            '2020-05-20',          // 27
//            '2020-05-21',          // 28
//            '2020-05-22',          // 29
// - новолуние '2020-06-21 6:42',
//            '2020-06-22',          // 1
//            '2020-06-23',      // 2
//            '2020-06-24',         // 3
//            '2020-06-25',         // 4
//            '2020-06-26',         // 5
//            '2020-06-27',         // 6
//            '2020-06-28',          //7
//            '2020-06-29',          //8
//            '2020-06-30',          //9
//            '2020-07-2',          //11
//            '2020-07-4',          // 13
//            '2020-07-6',          // 15
//            '2020-07-8',          // 17
//            '2020-07-10',          // 19
//            '2020-07-12',          // 21
//            '2020-07-14',          // 23
//            '2020-07-16',          // 25
//            '2020-07-18',          // 27
//            '2020-07-19',          // 28
//            '2020-07-20',          // 29
// - новолуние '2020-08-19 2:42',
//            '2020-08-20',          // 1
//            '2020-08-21',      // 2
//            '2020-08-22',         // 3
//            '2020-08-23',         // 4
//            '2020-08-24',         // 5
//            '2020-08-25',         // 6
//            '2020-08-26',          //7
//            '2020-08-27',          //8
//            '2020-08-28',          //9
//            '2020-08-30',          //11
//            '2020-09-1',          // 13
//            '2020-09-3',          // 15
//            '2020-09-5',          // 17
//            '2020-09-7',          // 19
//            '2020-09-9',          // 21
//            '2020-09-11',          // 23
//            '2020-09-13',          // 25
//            '2020-09-15',          // 27
//            '2020-09-16',          // 28
//            '2020-09-17',          // 29
// - новолуние '2020-10-16 19:32',
//            '2020-10-17',          // 1
//            '2020-10-18',      // 2
//            '2020-10-19',         // 3
//            '2020-10-20',         // 4
//            '2020-10-21',         // 5
//            '2020-10-22',         // 6
//            '2020-10-23',          //7
//            '2020-10-24',          //8
//            '2020-10-25',          //9
//            '2020-10-27',          //11
//            '2020-10-29',          // 13
//            '2020-10-31',          // 15
//            '2020-11-2',          // 17
//            '2020-11-4',          // 19
//            '2020-11-6',          // 21
//            '2020-11-8',          // 23
//            '2020-11-10',          // 25
//            '2020-11-12',          // 27
//            '2020-11-13',          // 28
//            '2020-11-14',          // 29
//            '2020-11-15',          // 30

// - новолуние '2020-11-15 5:09',
//            '2020-11-16',          // 1
//            '2020-11-17',      // 2
//            '2020-11-18',         // 3
//            '2020-11-19',         // 4
//            '2020-11-20',         // 5
//            '2020-11-21',         // 6
//            '2020-11-22',          //7
//            '2020-11-23',          //8
//            '2020-11-24',          //9
//            '2020-11-26',          //11
//            '2020-11-28',          // 13
//            '2020-11-30',          // 15
//            '2020-12-2',          // 17
//            '2020-12-4',          // 19
//            '2020-12-6',          // 21
//            '2020-12-8',          // 23
//            '2020-12-10',          // 25
//            '2020-12-12',          // 27
//            '2020-12-13',          // 28
//            '2020-12-14',          // 29
//            '2019-11-22',
            '2020-06-28',
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