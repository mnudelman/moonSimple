<?php
/**
 * Class AzimuthReport - отчёт о высоте и азимуте Солнца
 */

class AzimuthReport extends Report
{
    private $objectTab = [] ;        // географические координаты объектов
    private $dateTab = [] ;          // список дат для отчёта
    private $currentKey ;            // текущий ключ для выбора объекта
    private $udtObj = null ;         // объект UpDownTuning - расчёт точек восхода/заката
    private $aPoZObj = null;         // объект AnglePoZPhi - азимут в точках восхода/заката
    private $cpObj = null ;          // объект CyclePoints - точки расчёта
    private $haObj = null ;          // объект HeightAndAzimuth - азимут и высота над горизонтом
    private $upDnMbObj ;             // UpDnMontenbruck - алгоритмы от Монтенбрук ...
    private $orbitObj = null ;
    private $planetId ; //  - ид планеты
    private $orbitType  ; // - тип орбиты

    //---------------------------------------------//
    public function __construct() {
        $this->setDates() ;
        $this->setObjects() ;
        $this->upDnMbObj = new UpDnMontenbruck() ;
        $this->udtObj = new UpDownTuning() ;      // вычисление восхода/заката
        $this->aPoZObj = new AnglePoZPphi() ;  // азимут восхода/заката
        $this->cpObj = new CyclePoints();          // расчётные точки
        $this->haObj = new HeightAndAzimuth() ;  //  азимут и высота над горизонтом
        $this->planetId = Common::PLANET_ID_EARTH; //  - ид планеты
        $this->orbitType = Common::ORBIT_TYPE_ELLIPT ; // - тип орбиты
        $this->orbitObj = new Orbit();   // потребуется для вычисление  theta

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
        $key = (false === $key) ? $this->currentKey : $key;
        $this->currentKey = $key;
        $this->titleIni();
        $this->begTab();

        $r = $this->upDownClc() ;      // точки восхода/заката
//      -----------------------------------------------------------    //
        $date0 = $r['date0'] ;   // дата расчёта
        $this->cyclePointsClc($r) ;     // подготовить параметры цикла
        $theta0 = $r['point0']['theta'] ;    // пложение Pdl для начала суток
        $latitudeGrad = $this->objectTab[$this->currentKey]['lat'] ;
        $this->cycleDo($theta0,$date0,$latitudeGrad) ;

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
    private function cycleDo($theta0,$date0,$latitudeGrad) {
        $haO = $this->haObj ;     // объект HeightAndAzimuth

        $upDnMbObj = $this->upDnMbObj ;
        $lat = $this->objectTab[$this->currentKey]['lat'] ;
        $long = $this->objectTab[$this->currentKey]['long'] ;
        $upDnMbObj->setPoint($lat, $long) ;





        $haO->setTheta($theta0)
        ->setLatitude($latitudeGrad) ;      // широта

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
            $moonFlag = false ;    // значит Солнце
            $rMb = $upDnMbObj->azHClc($ts,$moonFlag) ;
            $azTest = $rMb['az'] ;
            $hTest =  $rMb['h'] ;

            $cpO->setAttribute('az-test',$azTest)  ;
            $cpO->setAttribute('h-test',$hTest)  ;

            switch ($type) {
                case 'p':        // простая точка расчёта
                    $this->heightAzimuthDo($psi,$dayTime) ;
                    break ;
                case 'pUp':      // восход
                    $cpO->setAttribute('height',0) ;
                    break ;
                case 'pDown':    // закат
                    $cpO->setAttribute('height',0) ;
                    break ;
                case 'p0' :      // полночь
                    $this->heightAzimuthDo($psi,$dayTime) ;
                    break ;
                case 'p1' :      // начало (x=1,y=0)
                    $this->heightAzimuthDo($psi,$dayTime) ;
                    break ;
                case 'pTheta' :  // поворот Pdl по текущенму времени ts
                    $theta = $this->getTheta($date0,$ts) ;
                    $haO->setTheta($theta) ;

                    break ;
            }
        }
//        $cpO->dumpPoints() ;
    }

    /**
     * перевычисление theta - положение Pdl
     */
    private function getTheta($date0,$ts) {

         $orbirObj = $this->orbitObj ;
        $orbirObj->setOrbitType($this->orbitType)   //тип орбиты (круговая|эллиптическая
        ->setPlanetId($this->planetId) //- ид планеты (Земля|Луна)
        ->setTestDT($date0);          //- тестовый момент для выбора параметров орбиты
        $theta = $orbirObj->getTheta($ts,true); // - центральный угол точки орбиты
        return $theta ;

    }
    private function heightAzimuthDo($psi,$dayTime) {
        $haO = $this->haObj ;     // объект HeightAndAzimuth
        $cpO = $this->cpObj ;
        $psiGrad = $psi / pi() * 180 ;
        $r = $haO->setBPointByAngle($psiGrad)
            ->getSunCoordinate() ;
        $h = $r['grad']['h'] ;
        $a = $r['grad']['aTuning'] ;
        $h = ($dayTime === Common::DAY_TIME_DARK) ? -$h : $h ;
        $cpO->setAttribute('height',$h)     // сохранить значения
        ->setAttribute('azimuth',$a) ;
    }
    /**
     * для выбора параметров цикла
     */
    private function getCycleAttributes() {
        $cycleUnitAngle = CyclePoints::CYCLE_UNIT_ANGLE ;
        $cycleUnitTime = CyclePoints::CYCLE_UNIT_TIME;
        return [
            'cycleUnit' => $cycleUnitTime , //$cycleUnitAngle,
            'beg' => '00:01',
            'end' => '23:59',
            'nSteps' => 80,
        ] ;
    }
    /**
     * настройка объекта CyclePoints
     * всё остаётся в объекте $this->cpObj
     */
    private function cyclePointsClc($rUpDown) {
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
        $psiInterval = $rUpDown['psiInterval'];
        $date = $this->dateTab[0] ;    // берём единственное значение

         $cycleAttr = $this->getCycleAttributes() ;     // назначенные атрибуты
        $cpO->setCurrentDate($date)           // текущая дата
        ->setPoint0($point0['psi'])                          // точка полночь
        ->setUpPoint( $upTimeFormat,$aUp,$upTheta,$upPsiGrad)    // восход
        ->setDownPoint($downTimeFormat,$aDn,$downTheta,$downPsiGrad) // закат
        ->setPsiInterval($psiInterval)       // угловые интервалы восход-закат и закат-восход
        ->setThetaTimes(['00:00:00','8:00:00','16:00:00'])        // моменты корректировки положения Pdl
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
        $date = $this->dateTab[0];    // берём единтвенное значение

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
    private function makeRow($r)
    {
        $ts = $r['ts'];
        $tf = $this->decomposeDate($ts, true);
        $time = $tf['h'] . ':' . $tf['i'] . ':' . $tf['s'];
        $psi = $r['psi'];
        $psiGrad = round($psi / pi() * 180, 4);
        $typePoint = $r['type'];
        $dayTime = $r['dayTime'];
        $height = round($r['height'], 4);
        $azimuth = round($r['azimuth'], 4);
        if (!is_null($r['az-test']) && !is_null($r['azimuth'])) {
            $azTest = round($r['az-test'], 3);
            $azDelta = round($azimuth - $azTest, 1);
        } else {
            $azTest = '   -';
            $azDelta = '   -';
        }
        if (!is_null($r['h-test']) && !is_null($r['height'])) {
            $hTest = round($r['h-test'], 2);
            $hDelta = round($hTest - $height, 1);
        } else {
            $hTest = '   -';
            $hTest = '   -';
        }

        $this->setCell('ts', $ts);
        $this->setCell('time', $time);
        $this->setCell('angle', $psiGrad);
        $this->setCell('type-point', $typePoint);
        $this->setCell('dayTime', $dayTime);
        $this->setCell('azimuth', $azimuth);
        $this->setCell('height', $height);

        $this->setCell('az-test', $azTest);
        $this->setCell('h-test', $hTest);
        $this->setCell('az-delta', '<b>' . $azDelta . '</b>');
        $this->setCell('h-delta', '<b>' . $hDelta . '</b>');

        $this->rowOut();
    }
    private function titleIni() {
        $key = $this->currentKey ;
        $town = $this->objectTab[$key]['name'] ;
        $lat =  $this->objectTab[$key]['lat'] ;
        $long =  $this->objectTab[$key]['long'] ;
        $date0 = $this->dateTab[0] ;
        $title = '<b>Азимут и высота Солнца</b><br>' .
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
//            '2019-10-10',
//            '2019-11-10',
            '2020-07-01',
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