<?php
/**
 * Class  MoonUpDnReport - отчёт о восходе/закате Луны
 */

class MoonUpDnMbruckReport extends Report
{
    private $objectTab = [] ;        // географические координаты объектов
    private $dateTab = [] ;          // список дат для отчёта
    private $currentKey ;            // текущий ключ для выбора объекта
    private $UpDnObj = null ;         // объект UpDownTuning - расчёт точек восхода/заката
    private $orbitObjM = null ;      // орбита Луны
    private $orbitObjE = null ;      // орбита Земли
    private $planetId ; //  - ид планеты
    private $orbitType  ; // - тип орбиты
    private $moonDateBeg ;
    private $moonDateEnd ;
    private $moonDateMiddle ;

    //---------------------------------------------//
    public function __construct() {
//     таблицы
        $this->setDates() ;
        $this->setObjects() ;

        $this->UpDnObj = new UpDnMontenbruck() ;    //  UpDownTuning() ;      // вычисление восхода/заката
        $this->planetId = Common::PLANET_ID_MOON; //  - ид планеты
        $this->orbitType = Common::ORBIT_TYPE_CIRCLE ; // - тип орбиты
        $this->orbitObjM = (new Orbit())   // потребуется для вычисление  theta
        ->setOrbitType($this->orbitType)   //тип орбиты (круговая|эллиптическая
        ->setPlanetId($this->planetId) ;   //- ид планеты (Земля|Луна)

        $this->orbitObjE = (new Orbit())   // потребуется для вычисление  theta
        ->setOrbitType(Common::ORBIT_TYPE_ELLIPT)   //тип орбиты (круговая|эллиптическая
        ->setPlanetId(Common::PLANET_ID_EARTH) ;   //- ид планеты (Земля|Луна)

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

       // подстраиваем под date0
        $date0 = $this->dateTab[0] ;
        $orbitObjM = $this->orbitObjM ;   // орбита Луны
        $orbitObjM->setTestDT($date0);

        $rMoonPar = $orbitObjM->getPar() ;
        $rMoonPer = $rMoonPar['period'] ;
        $tsBeg = strtotime($rMoonPer['dBeg']) ;
        $tsEnd = strtotime($rMoonPer['dEnd'])  ;
////-----------------------------------
        $this->moonDateBeg = $rMoonPer['d0'] ;
        $this->moonDateEnd = $rMoonPer['dEnd'] ;
        $this->moonDateMiddle = $rMoonPer['dMiddle'] ;

        $tfBeg = $this->decomposeDate($tsBeg,true) ;
        $dateBeg = $tfBeg['y'] . '-' . $tfBeg['m'] .  '-' . $tfBeg['d'] ;
        $dDayTs = 24 * 3600 ;
        $tsFirst = strtotime($dateBeg)  ;    // день новолуния
        $tfEnd = $this->decomposeDate($tsEnd,true) ;
        $dateEnd = $tfEnd['y'] . '-' . $tfEnd['m'] .  '-' . $tfEnd['d'] ;
        $tsLast = strtotime($dateEnd)  ;    // начало суток нового новолуния

        $lat = $this->objectTab[$this->currentKey]['lat'] ;
        $long = $this->objectTab[$this->currentKey]['long'] ;

        $upDnObj = $this->UpDnObj ;
        $vOut = $upDnObj->setPoint($lat, $long) ;

        $res = [] ;
        for ($i = 1; ($ts = $tsFirst + $i * $dDayTs) <= $tsLast; $i++ ) {
            $r = $vOut->setTime($ts,true)
                 ->upDownClc() ;
//            $r['dayNumber'] = $i ;
            $res[] = $r ;

        }
//        var_dump($res);
        $this->titleIni();
        $this->begTab();
        $this->tabPrint($res) ;
        $this->endTab();
    }

    /**
     * @param $r
     */
    private function tabPrint($r) {
        $dN = 0 ;
        for ($i = 0; $i < sizeof($r); $i++) {
            $row = $r[$i] ;
//            $dN = $row['dayNumber'] ;
            $dN = ($row[0]['type'] === 'up') ? $dN + 1 : $dN ;

            $this->makeRow($dN,$row[0]) ;
            if (isset($row[1])) {
                $dN = ($row[1]['type'] === 'up') ? $dN + 1 : $dN ;
                $this->makeRow($dN,$row[1]) ;
            }
        }
    }
    private function makeRow($dayNumber,$r) {
        $dayTime = $r['dt']  ;

        $dN = ($r['type'] == 'up') ? $dayNumber : '';
        $this->setCell('num',$dN) ;
        $this->setCell('type',$r['type']) ;
        $this->setCell('daytime',$dayTime) ;
        $this->setCell('azimuth',round($r['az'],3)) ;
        $this->setCell('h',round($r['h'],3)) ;
        $this->rowOut();
    }
    private function titleIni() {
        $key = $this->currentKey ;
        $town = $this->objectTab[$key]['name'] ;
        $lat =  $this->objectTab[$key]['lat'] ;
        $long =  $this->objectTab[$key]['long'] ;
        $date0 = $this->dateTab[0] ;
        $title = '<b>Восход, закат Луны втечении цикла(Монтенбрук)</b><br>' .
        '<b>город:</b> ' . $town . '<br>' .
        '<b>широта:</b> ' . $lat . '<br>' .
        '<b>долгота:</b> ' . $long . '<br>' .
        '<b>тест. дата:</b> ' . $date0 . '<br>' .
        '<b>начало(новолуние):</b> ' . $this->moonDateBeg . '<br>' .
        '<b>полнолуние:</b> ' . $this->moonDateMiddle . '<br>' .
        '<b>конец(след новолуние):</b> ' . $this->moonDateEnd . '<br>' ;

        $this->setTitle($title) ;
    }
    private function capIni() {
        $cap = [
            'num',
            'type',        // тип точки (0-восход; 1-закат)
            'daytime',     // время
            'azimuth',     // азимут
            'h',           // высота
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
// - новолуние '2020-02-24 15:34',
//            '2020-02-24 20:00',
//                '2020-02-25',      // 1
//            '2020-02-26',      // 2
// - новолуние '2020-03-24 9:30',
//            '2020-03-25',          // 1
//            '2020-03-26',      // 2

// - новолуние '2020-04-23 2:27',
//            '2020-04-24',          // 1
//            '2020-04-25',      // 2

// - новолуние '2020-05-22 17:40',
//            '2020-05-23',          // 1
//            '2020-05-24',      // 2

// - новолуние '2020-06-21 6:42',
//            '2020-06-22',          // 1
//            '2020-06-23',      // 2
// - новолуние '2020-07-20 17:34',
//            '2020-07-21',          // 1
            '2020-07-22',      // 2
//// - новолуние '2020-08-19 2:42',
////            '2020-08-20',          // 1
////            '2020-08-21',      // 2
//// - новолуние '2020-09-17 11:01',
////            '2020-09-18',          // 1
////            '2020-09-19',      // 2


// - новолуние '2020-10-16 19:32',
//            '2020-10-17',          // 1
//            '2020-10-18',      // 2
// - новолуние '2020-11-15 5:09',
//            '2020-11-16',          // 1
//            '2020-11-17',      // 2

            // - новолуние '2020-05-22 17:40',
//            '2020-05-24',          // 2

            // - новолуние '2020-10-16 19:32',
//            '2020-10-18',          // 2

// - новолуние '2020-11-15 5:09',
//            '2020-11-16',          // 1
//            '2020-11-17',      // 2
// - новолуние '2020-12-14 16:19',
//            '2020-12-15',          // 1
//            '2020-12-16',      // 2

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