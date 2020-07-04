<?php
/**
 * Class DayLength - отчёт о продолжительности дня и
 * времени восхода/заката
 */

class DayLengthReport extends Report
{
    private $objectTab = [] ;       // географические координаты объектов
    private $dateTab = [] ;             // список дат для отчёта
    private $currentKey ;               // текущий ключ для выбора объекта
    private $udt = null ;               // ссылка на объект UpDownTuning
    private $aPoZPhi = null;            // объект AnglePoZPhi
    private $planetId ; //  - ид планеты
    private $orbitType  ; // - тип орбиты

    //---------------------------------------------//
    public function __construct() {
        $this->setDates() ;
        $this->setObjects() ;
        $this->udt = new UpDownTuning() ;
        $this->aPoZPhi = new AnglePoZPphi() ;
        $this->planetId = Common::OBJECT_ID_EARTH; //  - ид планеты
        $this->orbitType = Common::ORBIT_TYPE_ELLIPT ; // - тип орбиты
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

        $this->titleIni() ;
        $this->begTab();

        $latitude = $this->objectTab[$key]['lat'] ;
        $longitude = $this->objectTab[$key]['long'] ;

        $udt = $this->udt ;
        for ($i = 0; $i < sizeof($this->dateTab); $i++) {
            $date = $this->dateTab[$i] ;
            $udt->setting($date,    //   - календарная дата
                $latitude,      //   - широта
                $longitude,
                $this->planetId, //  - ид планеты
                $this->orbitType ) ; // - тип орбиты
            $r = $udt->tuningDo1() ;
            $pDl = $r['pDl'] ;
            $pDl['p1']['theta'] = $r['up']['theta'] ;    // положение Pdl в момент восхода
            $pDl['p2']['theta'] = $r['down']['theta'] ;    // положение Pdl в момент восхода
            $tau = ($this->aPoZPhi)->
            setLatitudeAngle($latitude)->
            setPoints($pDl['p1'],$pDl['p2'])->angleClc() ;
            $aUp = $tau['azimuth'][0] ;
            $aDn = $tau['azimuth'][1] ;
            $r['azimuth']['up'] = (is_nan($aUp)) ? '  -' : $aUp ;
            $r['azimuth']['dn'] = (is_nan($aDn)) ? '  -' : $aDn ;
            $this->makeRow($r) ;

        }
        $this->endTab();


    }
    private function makeRow($r) {
        $this->setCell('date',$r['date0']) ;
        $totalTime = $r['totalTime'] ;

        $dtCorr = $totalTime['dayTimeCorr'] ;
        $hm = $dtCorr['h'] .':' . $dtCorr['m'] .':' . $dtCorr['s'] ;
        $this->setCell('dayLengthCorr','<i>' . $hm . '</i>') ;

        $dtTuning = $totalTime['dayTimeTuning'] ;
        $hm = $dtTuning['h'] . ':' . $dtTuning['m']  . ':' . $dtTuning['s'] ;
        $this->setCell('dayLength',$hm) ;

        $this->setCell('dayLength-test','<b>'. $r['dayTimeDSI'] .'</b>') ;

//        $upCorr = $r['up']['timeCorrFormat'] ;
//        $hm = $upCorr['h'] .':' . $upCorr['m'] .':' .  $upCorr['s'] ;
        $upCorr = $r['up']['timeCorrFormat'] ;
        $hm = $upCorr['h'] .':' . $upCorr['i'] .':' .  $upCorr['s'] ;

        $this->setCell('sunriseCorr','<i>' . $hm . '</i>') ;

        $upLong = $r['up']['timeLongFormat'] ;
        $hm = $upLong['h'] . ':' . $upLong['i']  . ':' . $upLong['s'] ;
        $this->setCell('sunrise',$hm) ;



        $this->setCell('sunrise-test',
            '<b>' . $r['DSI']['format']['sunrise'] .'</b>') ;

        $dnCorr = $r['down']['timeCorrFormat'] ;
        $hm = $dnCorr['h'] .':' . $dnCorr['i'] .':' . $dnCorr['s'] ;
        $this->setCell('sunsetCorr','<i>' . $hm . '</i>') ;


        $dnLong = $r['down']['timeLongFormat'] ;
        $hm = $dnLong['h'] . ':' . $dnLong['i'] . ':' . $dnLong['s'] ;
        $this->setCell('sunset',$hm) ;

        $this->setCell('sunset-test',
            '<b>' . $r['DSI']['format']['sunset'] . '</b>') ;

        $this->setCell('azimuth-sunrise',$r['azimuth']['up']) ;
        $this->setCell('azimuth-sunset',$r['azimuth']['dn']) ;
        $this->rowOut();
    }
    private function titleIni() {
        $key = $this->currentKey ;
        $town = $this->objectTab[$key]['name'] ;
        $lat =  $this->objectTab[$key]['lat'] ;
        $long =  $this->objectTab[$key]['long'] ;
        $title = '<b>Продолжительность дня</b><br>' .
        '<b>город:</b> ' . $town . '<br>' .
        '<b>широта:</b> ' . $lat . '<br>' .
        '<b>долгота:</b> ' . $long . '<br>' ;
        $this->setTitle($title) ;
    }
    private function capIni() {
        $cap = [
            'date',                 // дата
            'dayLengthCorr',        // продолжительность с поправкой на равноденствие
            'dayLength',            // продолжительность дня без поправки
            'dayLength-test',       // тестовая прдолжительность
            'sunriseCorr',          // время восхода с поправкой на равноденствие
            'sunrise',              // время восхода
            'sunrise-test',         // время восхода тестовое
            'sunsetCorr',           // время заката  с поправкой на равноденствие
            'sunset',               // время заката
            'sunset-test',          // тестовое время заката
            'azimuth-sunrise',      // азимут восхода
            'azimuth-sunset',       // азимут заката
        ] ;
        $this->setCap($cap) ;
    }
    private function setDates() {
        $this->dateTab = [] ;

        $this->dateTab = [
//            '2019-01-05',
//            '2019-01-10',
//            '2019-01-12',
//            '2019-01-15',
//            '2019-01-17',
//            '2019-01-20',
//            '2019-02-05',
//            '2019-02-10',
//            '2019-02-15',
//            '2019-02-20',
//            '2019-02-25',
//            '2019-03-05',
//            '2019-03-10',
//            '2019-03-20',
//            '2019-03-25',
//            '2019-03-30',
//            '2019-04-05',
//            '2019-05-10',
//            '2019-05-15',
//            '2019-05-20',
//            '2019-05-25',
//            '2019-05-30',
//            '2019-06-05',
//            '2019-06-07',
//            '2019-06-10',
//            '2019-06-12',
//            '2019-06-15',
//            '2019-06-17',
//            '2019-06-20',
//            '2019-06-25',
//            '2019-07-10',
//            '2019-08-10',
//            '2019-09-10',
//            '2019-09-15',
//            '2019-09-20',
//            '2019-09-25',
//            '2019-10-10',
//            '2019-11-10',
//            '2019-12-05',
//            '2019-12-10',
//            '2019-12-15',
//            '2019-12-20',
//            '2019-12-25',
            '2020-01-25',
            '2020-01-26',
            '2020-01-27',
            '2020-01-28',
            '2020-01-29',
            '2020-01-30',
            '2020-01-31',
            '2020-02-01',
            '2020-02-02',
            '2020-02-03',
            '2020-02-04',
            '2020-02-04',
            '2020-02-05',
            '2020-02-06',
            '2020-02-07',
//            '2020-02-08',
//            '2020-02-09',
//            '2020-02-10',
//            '2020-02-11',

            '2020-06-09',
            '2020-06-10',
            '2020-06-11',

            '2020-09-09',
            '2020-09-10',
            '2020-09-11',

            '2020-12-09',
            '2020-12-10',
            '2020-12-11',

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