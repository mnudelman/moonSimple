<?php
/**
 * Class UpDownTuning
 * подстройка точек восхода/заката
 * $udt->setting($date0,      - календарная дата
 * $phi,                     - широта
 * $planetId,                - ид планеты
 * $orbitType )               - тип орбиты
 * вычисление:
 *  $r = $udt->tuningDo()
 */

class UpDownTuning extends Common
{
    private $date0;      // дата, по которой надо вполнить подстрройку - полночь
    private $theta0 ;    // угол соотв $date0
    private $latitudeAngle ;       // широта
    private $longitudeAngle ;      // долгота
    private $orbitObj ;
    private $orbiEarthObj ;
    private $lsObj ;
    private $timeEpsilon = 60; //300 ; //180; //60; //180 ; //180 ; // sec - точность определния времени восхода/заката
    private $psi0 = false;   // положение точки - полночь, соотв $theta0 (половина дуги,
    private $psi0Earth ;     // нужна Земля отдельно от Луны
    private $moonTheta0 = 0 ;    // добавка для пересчёта угла theta, вычисленного по орбите Луны
                                 // в систему XoYZ, где theta=0 на оси oX
    private $orbitDate0 ;   // дата текущего начала отсчёта(Земля - равноденствие; Луна - новолуния
    private $orbitPeriod ;  // период обращения
    private $orbitDPerBeg ; // начало периода обращения (Земля - перигелий; Луна - новолуние)
    private $orbitDPerMiddle ; // редина периода (напр, полнолуние)

    private $orbitDPerEnd ; // окончание периода

    private $up = [     // все атрибуты для восхода
        'dts0min' => 0,     // начальное приближение времени
        'psi0grad' => 0,     // начальный угол
        'dtsMin'  => 0,     // конечное приближение времени
        'psiGrad'  => 0,     // конечный угол
        'theta' => 0,        // конечное положение плоскости
        'p0' => [],          // атибуты начальной точки
    ] ;
    private $down = [] ; // все атрибуты для заката
    private $p0 = [      // текущая начальная точка
        'theta' => 0,
        'ts' => 0,
        'psi' => 0,
    ] ;

    private $planetId ;
    private $orbitType ;
    private $corrTime = [      // корректировка по времени равноденствия
        'dTotSec' => 0, // продолжтельность
        'dUpSec' => 0,   // восход
        'dDnSec' => 0,   // закат
    ] ;
    private $polarDay = false ;     // полярный день
    private $polarNight = false ;
    protected $pDl = ['p1' => [],'p2' => []] ;   // точки восхода/заката на сечении Pphi
    protected $psiInterval = [] ;     // начало/конец центрального угла для светлой/тёмной части
    private $dtsUpDn = null ;     // результат расчёта времени от начала суток до восхода/заката
    private $firstDay = [         // первый день лунного цикла
        'date' => '',
        'point0' => [],
        'up' => [],
        'down' => [],
    ] ;
//------------------------------------------------------------------//
    public function __construct()
    {
        $this->orbitObj = new Orbit();
        $this->lsObj = new LatitudeSection();

    }

    public function setting($date0,$lutAngle,$longAngle = 0,$planetId = false,$orbitType = false)
    {
        $this->date0 = $date0;
        $this->latitudeAngle = $lutAngle ;      // широта
        $this->longitudeAngle = $longAngle ;    // долгота
        $this->planetId = (false === $planetId) ? self::PLANET_ID_EARTH : $planetId;
        $this->orbitType = (false === $orbitType) ? self::ORBIT_TYPE_ELLIPT : $orbitType ;
//        $this->init() ;
        $this->initOrbit() ;
        if ($this->planetId === Common::PLANET_ID_EARTH) {
            $this->corrTime = $this->corrTimeClc() ;   // корректировка по контрольному значению
        }
        return $this;
    }
    /**
     * параметры орбиты
     */
    private function initOrbit() {
        $orbitObj = $this->orbitObj ; // new Orbit();
        $lsObj = $this->lsObj ; //   new LatitudeSection();

        $orbitObj->setOrbitType($this->orbitType)   //тип орбиты (круговая|эллиптическая
        ->setPlanetId($this->planetId) //- ид планеты (Земля|Луна)
        ->setTestDT($this->date0);          //- тестовый момент для выбора параметров орбиты
//        $this->theta0 = $orbitObj->getTheta($this->date0); // - центральный угол точки орбиты
        $lsObj->setLatitudeAngle($this->latitudeAngle) ;       //-  широта


        $rOrbitPar = $orbitObj->getPar() ;
        $this->orbitDate0 = $rOrbitPar['period']['d0'] ;   // дата начала отсчёта
                          // ( Земля - равноденстви; Луна - новолуние)
        $this->orbitPeriod = $rOrbitPar['period']['T'] ;  // период (дней)
        $this->orbitDPerBeg = $rOrbitPar['period']['dBeg'] ;  // начало периода(дата)
        $this->orbitDPerEnd = $rOrbitPar['period']['dEnd'] ;  //окончание периода(дата)
        if ($this->planetId === Common::PLANET_ID_MOON) {
            $this->orbitDPerMiddle = $rOrbitPar['period']['dMiddle'];  //полнолуние(дата)
        }

        return $this ;
    }
    /**
     * начальное положение точек восхода/заката
     * theta0  - положение для даты расчёта date0
     * moonTheta0 - это начальный поворот для момента новолуния
     *              естественно только для Луны
     * Если речь идёт о Луне, то это плоскость P_moon_face
     * Если Земля - плоскость Pdl
     * @return $this
     */
    private function upDownInit() {
        $notMoonFlag = ($this->planetId !== Common::PLANET_ID_MOON) ;
        if ($notMoonFlag) {
            $this->moonTheta0 = 0 ;
        }
        $orbitObj = $this->orbitObj ;
        $this->theta0 = $orbitObj->getTheta($this->date0); // - центральный угол точки орбиты

        $lsObj = $this->lsObj ; //   new LatitudeSection();
        $lsObj->setOZPlane($this->theta0 + $this->moonTheta0);  // секущая плоскость (например, Pdl
        $r = $lsObj->intersectPointsClc();
        $psiIntervalDark = $r['psiInterval'][self::DAY_TIME_DARK]; // дуга с тёмной стороны
        $psiIntervalLight = $r['psiInterval'][self::DAY_TIME_LIGHT]; // дуга со светлой стороны
        $psiBegDark = $psiIntervalDark[0];
        $psiEndDark = $psiIntervalDark[1];
        $psiBegLight = $psiIntervalLight[0];
        $psiEndLight = $psiIntervalLight[1];
        // начальное состояние
        $this->pDl = ['p1' => $r['p1'],'p2' => $r['p2']] ;
        $this->psiInterval = $r['psiInterval'] ;

        //-----------------------------------------
        if ($notMoonFlag) {      // для Луны psi0 вычисляется в initMoon

            $this->psi0 = ($psiBegDark + $psiEndDark) / 2;    // полночь
        }
        // расчёт минут до восхода/заката
        $dtsMin = $this->dtsClc($this->psi0,$this->psiInterval) ;
        $this->dtsUpDn = $dtsMin ;

        $this->up['dts0min'] = $dtsMin['up']['min'] ; // минут до восхода - начальное приближение времени
        $this->up['psi0grad'] = $dtsMin['up']['grad'] ; //rad2deg($psiEndDark);

        $this->up['dtsMin'] = $this->up['dts0min'] ; // минут до восхода - начальное приближение времени
        $this->up['psiGrad'] = $this->up['psi0grad'] ; //rad2deg($psiEndDark);
        $this->up['theta'] = $this->theta0 + $this->moonTheta0 ;


        $this->down['dts0min'] = $dtsMin['dn']['min'] ; // минут до заката
        $this->down['psi0grad'] = $dtsMin['dn']['grad'] ; //rad2deg($psiBegDark);
        $this->down['dtsMin'] = $this->down['dts0min'] ; // минут до заката
        $this->down['psiGrad'] = $this->down['psi0grad'] ; //rad2deg($psiBegDark);
        $this->down['theta'] = $this->theta0 + $this->moonTheta0 ;
// сравнить по времени - это полная ерунда !!!
//        $this->orbitObj = $orbitObj;
//        $this->lsObj = $lsObj;
        $this->polarDay = ($psiEndDark - $psiBegDark == 0);
        $this->polarNight = ($psiEndLight - $psiBegLight == 0);
        if ($this->polarDay || $this->polarNight) {
            $this->corrTime = [      // корректировка по времени равноденствия
                'dTotSec' => 0, // продолжтельность
                'dUpSec' => 0,   // восход
                'dDnSec' => 0,];   // закат
        }
        return $this ;

    }
    /**
     * здесь параметры для луны - это для P_moon_face
     * точка psi0 - это полночь. Расчитывается положения  Pdl
     * для даты расчёта date0
     * @return $this
     */
    private function initMoon() {
        $lsObj = $this->lsObj ; //   new LatitudeSection();
        $orbitObj = $this->orbitObj ; // это орбита планеты
        if ($this->planetId === Common::PLANET_ID_MOON) {
//            $rMoonPar = $orbitObj->getPar() ;
//            $this->orbitDate0 = $rMoonPar['period']['d0'] ;   // дата новолуния
//            $rMoonPar = $orbitObj->getPar() ;
//            $this->orbitDate0 = $rMoonPar['period']['d0'] ;   // дата новолуния
//            $this->orbitPeriod = $rMoonPar['period']['T'] ;  // период (дней)





            $orbirEarth = new Orbit();
            $this->orbiEarthObj = $orbirEarth ;
            $orbirEarth->setOrbitType(Common::ORBIT_TYPE_ELLIPT)   //тип орбиты (круговая|эллиптическая
            ->setPlanetId(Common::PLANET_ID_EARTH) //- ид планеты (Земля|Луна)
            ->setTestDT($this->date0)  ;        //- тестовый момент для выбора параметров орбиты

            $this->moonTheta0 = $orbirEarth->getTheta($this->orbitDate0) ;
            // надо здесь поставить полночь
            $date0Theta =  $orbirEarth->getTheta($this->date0) ;
//            $date0Theta =  $orbirEarth->getTheta($this->orbitDate0) ;
            $lsObj->setOZPlane($date0Theta);  // секущая плоскость (например, Pdl
            $r = $lsObj->intersectPointsClc();
            $psiIntervalDark = $r['psiInterval'][self::DAY_TIME_DARK]; // дуга с тёмной стороны
            $psiBegDark = $psiIntervalDark[0];
            $psiEndDark = $psiIntervalDark[1];
            //-----------------------------------------


            $this->psi0 = ($psiBegDark + $psiEndDark) / 2 ;    // полночь
            $this->psi0Earth = ($psiBegDark + $psiEndDark) / 2;    // полночь
// можно подправить

            $dSI = $this->dateSunInfo($this->date0,
                $this->latitudeAngle,0) ;
            $ts0 = strtotime($this->date0) ;
            $dtsUp = $dSI['dsi']['sunrise'] - $ts0 ;
            $dtsDn = $dSI['dsi']['sunset'] - $ts0 ;
            $dtsDn24 = 24*3600 - $dtsDn ;
            $dts = $dtsUp - $dtsDn24  ; // попробуем без корректировок
            $dpsiGrad = (-$dts / 60) / Common::MINUTES_IN_DEGREE ;
            $dpsi = deg2rad($dpsiGrad) ;
            $this->psi0 += $dpsi ;
            $this->psi0Earth += $dpsi ;

//            $this->psi0Tuning() ;
            $this->moonOrbitTuning() ;     // граничные условия для орбиты Луны
//            $rMoonPer = [] ;
//            $rMoonPer['dBeg'] = $this->orbitDPerBeg   ;  // начало периода(дата)
//            $rMoonPer['dEnd'] = $this->orbitDPerEnd   ;  //окончание периода(дата)
//            $rMoonPer['dMiddle'] = $this->orbitDPerMiddle  ;  //полнолуние(дата)
//
//// запихиваем контрольные точки
//// попробуем deltaMinus
//$deltaMinus =  0 ; //- 24*3600 ;
//            $eOrbitObj = $orbirEarth ;
//            $mOrbitObj = $orbitObj ;
//            $thetaMoon0 = $this->moonTheta0 ;
//            $ts = strtotime($rMoonPer['dBeg'])  + $deltaMinus;
//            $theta = $eOrbitObj->getTheta($ts,true)  - $thetaMoon0;
//            $mOrbitObj->setControlPoint($ts,$theta) ;
//            $ts = strtotime($rMoonPer['dMiddle']) + $deltaMinus ;
//            $theta = $eOrbitObj->getTheta($ts,true)  - $thetaMoon0 ;
//            $mOrbitObj->setControlPoint($ts,$theta + pi()) ;
//            $ts = strtotime($rMoonPer['dEnd']) + $deltaMinus;
//            $theta = $eOrbitObj->getTheta($ts,true)  - $thetaMoon0  ;
//            $mOrbitObj->setControlPoint($ts,$theta + 2*pi()) ;





//          получилось точно время восхода
//            $r1 = $this->dateSunInfo($this->date0,
//                $this->latitudeAngle,$this->longitudeAngle) ;
        }
        return $this ;
    }

    /**
     * использовать в параметрах орбиты Луны граничные значения:
     * известны точные моменты новолуния,полнолуния, след. новолуния.
     * Им соответствуют точки орбиты Луны с углом отклонения от новолуния:
     * 0, pi , 2*pi. Т.о. орбита Луны описывается кусочно-линейной фунццией с
     * преломлением в названных точках
     *$rMoonPer - таблица контрольных точек [name => ['ts' => ..,'dTheta' => ..] ]
     */
    private function moonOrbitTuning() {
        // запихиваем контрольные точки
        $eOrbitObj = $this->orbiEarthObj ;
        $mOrbitObj = $this->orbitObj ;
        $thetaMoon0 = $this->moonTheta0 ;

        $rMoonPer = [] ;
        $rMoonPer['dBeg'] =['date' => $this->orbitDPerBeg,'dTheta' => 0]   ;  // начало периода(дата)
        $rMoonPer['dMiddle'] = ['date' => $this->orbitDPerMiddle,'dTheta' => pi()]  ;  //полнолуние(дата)
        $rMoonPer['dEnd'] = ['date' => $this->orbitDPerEnd,'dTheta' => 2*pi()]   ;  //окончание периода(дата)
        foreach ($rMoonPer as $pointName => $arr) {
            $ts = strtotime($arr['date']) ;
            $dTheta = $arr['dTheta'] ;
            $theta = $eOrbitObj->getTheta($ts,true)  - $thetaMoon0 + $dTheta;
            $mOrbitObj->setControlPoint($ts,$theta) ;
        }
        return true ;
    }
    private function psi0Tuning() {
        $lsObj = $this->lsObj ; //   new LatitudeSection();
        $orbitObj = $this->orbitObj ; // это орбита планеты
        $orbirEarth = $this->orbiEarthObj   ;

        $dSI = $this->dateSunInfo($this->date0,
            $this->latitudeAngle,0) ;
        $ts0 = strtotime($this->date0) ;
         $dts = 3600 ;
         $lightTime0 =  $dSI['dsi']['sunset'] - $dSI['dsi']['sunrise'] ;
        $darkTH0 = 24 - ($lightTime0 / 3600) ;
        for ($i = 0; $i < 72; $i++) {
            $date0Theta =  $orbirEarth->getTheta($ts0 + $i * $dts,true) ;
//            $date0Theta =  $orbirEarth->getTheta($this->orbitDate0) ;
            $lsObj->setOZPlane($date0Theta);  // секущая плоскость (например, Pdl
            $r = $lsObj->intersectPointsClc();
            $psiIntervalDark = $r['psiInterval'][self::DAY_TIME_DARK]; // дуга с тёмной стороны
            $psiBegDark = $psiIntervalDark[0];
            $psiEndDark = $psiIntervalDark[1];
            $deltaPsiGrad = rad2deg($psiEndDark - $psiBegDark) ;
            $darkTime = $deltaPsiGrad * Common::MINUTES_IN_DEGREE ;
            $darkTH = $darkTime / 60 ;
            echo 'deltDarkT: ' .$i . ': '. round($darkTH0 - $darkTH,3) . '<br>' ;
        }
    }
    /**
     * вычисляет время от полуночи (00:00:00) до восхода/заката в минутах
     * psi0 - центр угол, соотв полночи
     * все угловые отметки interval'a выстраиваются на оси т.о., что
     * psi0 - начало, далее все остальные в порядке возрастания
     */
    private function dtsClc($psi0,$psiInterval,$dtsPrev = null) {
        $psiIntervalDark = $psiInterval[self::DAY_TIME_DARK]; // дуга с тёмной стороны
        $psiIntervalLight = $psiInterval[self::DAY_TIME_LIGHT]; // дуга со светлой стороны
        $a = [
            'd' => [$psiIntervalDark[0],$psiIntervalDark[1]],
            'l' => [$psiIntervalLight[0],$psiIntervalLight[1]],
        ] ;
        foreach ($a as $key => $value) {
            $k1 = true ;
            $k2 = true ;
            while ($k1 || $k2) {
                $k1 = ($a[$key][0] < $psi0) ;
                if ($k1) {
                    $a[$key][0] += 2 * pi() ;
                }
                $k2 = ($a[$key][1] < $psi0) ;
                if ($k2) {
                    $a[$key][1] += 2 * pi() ;
                }
            }
            if ($a[$key][1] < $a[$key][0]) {
                $a[$key][1] += 2 * pi() ;
            }
        }
        $dts = [] ;
        // восход раньше заката - правило
        if ($a['d'][0] < $a['l'][0]) {
//            $a['d'][0] += 2 * pi() ;
        }
        $dts['dn']['grad'] = rad2deg($a['d'][0]) ;
        $dts['up']['grad'] = rad2deg($a['l'][0]) ;



        if (!is_null($dtsPrev)) {      // корректировка в случае полного круга
            while ($dts['dn']['grad'] <  $dtsPrev['dn']['grad']) {
                $dts['dn']['grad'] += 360 ;
            }
            while ($dts['up']['grad'] <  $dtsPrev['up']['grad']) {
                $dts['up']['grad'] += 360 ;
            }
        }
        $psi0Grad = rad2deg($psi0) ;
        $dts['dn']['min'] = ($dts['dn']['grad'] - $psi0Grad) * self::MINUTES_IN_DEGREE ;
        $dts['up']['min'] = ($dts['up']['grad'] - $psi0Grad) * self::MINUTES_IN_DEGREE ;

        return $dts ;
    }
    /**
     * расчёт корректирующих минут
     * Предпологается для равноденствия
     * t_day = 12 ; t_up = 6 ; t_dn = 18 ;
     *  dt_long - поправка на долготу
     * корректировка пересчитывает поправки на факт. атрибуты
     * равноденствия
     */
    private function corrTimeClc() {
        $par = $this->orbitObj->getPar() ;
        $d0 = $par['period']['d0'] ;   // дата равноденствие
        $dsi = $this->dateSunInfo($d0,$this->latitudeAngle,
                                    $this->longitudeAngle) ;
        $dtLongMinutes = $this->longitudeTime() ; // поправка на долготу
        $upMin = 6 * 60 + $dtLongMinutes ;
        $dnMin = 18 * 60 + $dtLongMinutes ;
        $bsF = $this->decomposeDate($d0) ;
        $dsBaseDate = strtotime(($bsF['y'] .'-' .
            $bsF['m'] . '-' . $bsF['d'])) ;
        $dUpSec = $dsi['dsi']['sunrise'] -  ($dsBaseDate + $upMin * 60) ;
        $dDnSec = $dsi['dsi']['sunset'] - ($dsBaseDate + $dnMin * 60) ;
        $dTotSec = $dDnSec - $dUpSec ;
        return [
            'dTotSec' => round($dTotSec,0), // продолжтельность
            'dUpSec' => round($dUpSec,0),   // восход
            'dDnSec' => round($dDnSec,0),   // закат
        ] ;
    }


    public function tuningDo()
    {
        $this->initMoon()
            ->upDownInit() ;
// здесь определить $p0
        $this->p0['psi'] = $this->psi0 ;
        $this->p0['theta'] = $this->theta0  + $this->moonTheta0 ;
        $this->p0['ts'] = strtotime($this->date0) ;
//--------------------------------------------------
        $polarFlag = $this->polarNight || $this->polarDay ;  // полярный день или ночь
        if ($polarFlag) {
            $this->up = $this->polarState($this->up) ;
            $this->down = $this->polarState($this->down) ;
        } else {
// сравнить время и первым вести расчёт для меньшего времени
            $dtsUp = $this->up['dtsMin'];
            $dtsDown = $this->down['dtsMin'];
            if ($dtsUp < $dtsDown) {
                $arr = [];
//                for ($i = 0; $i < 29; $i++) {
                $this->up = $this->tuningDoPoint('up');
                $this->up['p0'] = $this->p0;
                $arr[] = $this->up;
                $this->p0['psi'] = deg2rad($this->up['psiGrad']);
                $this->p0['theta'] = $this->up['theta'];
                $this->p0['ts'] = $this->p0['ts'] + $this->up['dtsMin'] * 60;

                $this->down = $this->tuningDoPoint('down');
                $this->down['p0'] = $this->p0;

                $arr[] = $this->down;

                $this->p0['psi'] = deg2rad($this->down['psiGrad']);
                $this->p0['theta'] = $this->down['theta'];
                $this->p0['ts'] = $this->p0['ts'] + $this->down['dtsMin'] * 60;

            } else {
                $arr = [];

                $this->down = $this->tuningDoPoint('down');
                $arr[] = $this->down;

                $this->down['p0'] = $this->p0;

                $this->p0['psi'] = deg2rad($this->down['psiGrad']);
                $this->p0['theta'] = $this->down['theta'];
                $this->p0['ts'] = $this->p0['ts'] + $this->down['dtsMin'] * 60;


                $this->up = $this->tuningDoPoint('up');
                $arr[] = $this->up;
                $this->p0['psi'] = deg2rad($this->up['psiGrad']);
                $this->p0['theta'] = $this->up['theta'];
                $this->p0['ts'] = $this->p0['ts'] + $this->up['dtsMin'] * 60;

            }


//          надо переопределить интервалы по точкам $this->pDl
            $p1 = $this->up['point'];
            $p2 = $this->down['point'];
            $this->pDl['p1'] = $p1;
            $this->pDl['p2'] = $p2;
            $lso = $this->lsObj;    // это LatitudeSection
            $this->psiInterval = ($lso->
            setIntersectionPoints($p1, $p2))->getPsiInterval();
        }
//     продолжительность дня общая
        $this->down['dts0min'] += $this->up['dts0min'] ;
        $this->down['dtsMin'] += $this->up['dtsMin'] ;
        $totalTime = (!$polarFlag) ? $this->tuningDoTotal() : $this->polarDayTotal() ;
//      тестовая продолжительность
        $dSI = $this->dateSunInfo($this->date0,
            $this->latitudeAngle,$this->longitudeAngle) ;
        $dayLength = $dSI['dsi']['sunset'] - $dSI['dsi']['sunrise'] ;
        $dl = $this->decomposeDate($dayLength,true) ;
        $dlFormat = $dl['h'] .':' . $dl['i'] . ':' . $dl['s'] ;
        return  [
            'date0' => $this->date0,      // дата, по которой надо вполнить подстрройку
            'point0' => [                // начальная точка(напр, полночь)
                'theta' => $this->theta0,    // угол плоскости Pdl соотв $date0
                'psi' => $this->psi0,   // положение точки на Pphi - полночь, соотв $theta0 (половина дуги,
                'psiEarth' => $this->psi0Earth,
            ],
            'latitude' => $this->latitudeAngle,
            'longitude' => $this->longitudeAngle,
            'epsilonSec' => $this->timeEpsilon,  // sec - точность определния времени восхода/заката
            'totalTime' => $totalTime,
            'dayTimeDSI' => $dlFormat,  // тестовая продолжительность
            'DSI' => $dSI,              // тестовые данные по date_sun_info()
            'up' => $this->up,          // точка восхода
            'down' => $this->down,      // точка заката
            'corrTime' => $this->corrTime,
            'pDl' => $this->pDl,
            'psiInterval' => $this->psiInterval,
            'moonTheta0' => $this->moonTheta0,
            'arr' => $arr,




        ];
    }


    private function oneDateUpDown($date)
    {
        $currentDate = $this->date0;
        $this->date0 = $date;
        $this->initMoon()
            ->upDownInit();
// здесь определить $p0
        $polarFlag = $this->polarNight || $this->polarDay ;  // полярный день или ночь
        if ($polarFlag) {
            $this->up = $this->polarState($this->up) ;
            $this->down = $this->polarState($this->down) ;
            $pOrder = ['up' => $this->up, 'down' => $this->down , 'polarFlag' => true] ;
        } else {
            $this->p0['psi'] = $this->psi0;
            $this->p0['theta'] = $this->theta0 + $this->moonTheta0;
            $this->p0['ts'] = strtotime($this->date0);

// полнейшая уйня
//            $ts = ($this->orbiEarthObj)->getTs($this->theta0 + $this->moonTheta0) ;
//            $tf = $this->decomposeDate($ts,true) ;
//            var_dump($tf);
////            $this->p0['ts'] = $ts ;
//
//            $tf0 = $this->decomposeDate($this->date0) ;
//            $d0 = $tf0['y'] . '-'  . $tf0['m'] . '-'  . $tf0['d'] . ' '  .
//                $tf['h'] . ':'  . $tf['i'] . ':'  . $tf['s']  ;
//            $this->p0['ts'] = strtotime($d0) ;

// проба с лунным временем
//            $this->p0['ts'] = strtotime($this->date0) - strtotime($this->orbitDate0) ;

            $tsStart = $this->p0['ts'] ;
            $tsUp = $tsStart + $this->up['dtsMin'] * 60;
            $tsDown = $tsStart + $this->down['dtsMin'] * 60;
//            $pOrder = ($tsUp < $tsDown) ? ['up' => $this->up, 'down' => $this->down] :
//                ['down' => $this->down, 'up' => $this->up];

            $pOrder = ['up' => $this->up, 'down' => $this->down] ;

            foreach ($pOrder as $key => $point) {
                $point = $this->tuningDoPoint($key);
                $point['p0'] = $this->p0;
                $pOrder[$key] = $point ;
                $this->p0['psi'] = deg2rad($point['psiGrad'] );
                $this->p0['theta'] = $point['theta'];
                $this->p0['ts'] = $this->p0['ts'] + $point['dtsMin'] * 60;
            }
            $pOrder['polarFlag'] = false ;
        }
        return $pOrder ;
    }

    public function tuningDo1()
    {
        $r = $this->oneDateUpDown($this->date0);
        $this->up = $r['up'];
        $this->down = $r['down'];
        $polarFlag = $r['polarFlag'];
        $arr = [] ;
        $arr[] = $this->up ;
        $arr[] = $this->down ;
//          надо переопределить интервалы по точкам $this->pDl
        $p1 = $this->up['point'];
        $p2 = $this->down['point'];
        $this->pDl['p1'] = $p1;
        $this->pDl['p2'] = $p2;
        $lso = $this->lsObj;    // это LatitudeSection
        $this->psiInterval = ($lso->
        setIntersectionPoints($p1, $p2))->getPsiInterval();
//     продолжительность дня общая
//        $this->down['dts0min'] += $this->up['dts0min'];
//        $this->down['dtsMin'] += $this->up['dtsMin'];
        $totalTime = (!$polarFlag) ? $this->tuningDoTotal() : $this->polarDayTotal();
//      тестовая продолжительность
        $dSI = $this->dateSunInfo($this->date0,
            $this->latitudeAngle, $this->longitudeAngle);
        $dayLength = $dSI['dsi']['sunset'] - $dSI['dsi']['sunrise'];
        $dl = $this->decomposeDate($dayLength, true);
        $dlFormat = $dl['h'] . ':' . $dl['i'] . ':' . $dl['s'];
        return [
            'date0' => $this->date0,      // дата, по которой надо вполнить подстрройку
            'point0' => [                // начальная точка(напр, полночь)
                'theta' => $this->theta0,    // угол плоскости Pdl соотв $date0
                'psi' => $this->psi0,   // положение точки на Pphi - полночь, соотв $theta0 (половина дуги,
                'psiEarth' => $this->psi0Earth,
            ],
            'latitude' => $this->latitudeAngle,
            'longitude' => $this->longitudeAngle,
            'epsilonSec' => $this->timeEpsilon,  // sec - точность определния времени восхода/заката
            'totalTime' => $totalTime,
            'dayTimeDSI' => $dlFormat,  // тестовая продолжительность
            'DSI' => $dSI,              // тестовые данные по date_sun_info()
            'up' => $this->up,          // точка восхода
            'down' => $this->down,      // точка заката
            'corrTime' => $this->corrTime,
            'pDl' => $this->pDl,
            'psiInterval' => $this->psiInterval,
            'moonTheta0' => $this->moonTheta0,
            'arr' => $arr,


        ];
    }







    /**
     * на входе вектор точки (восхода/заката) в начальном состоянии:
     * атрибуты ['dts0min'], ['psi0grad']
     *  Здесь три корректировки: 1. догонялка ; 2. по долготе ; 3. по равнодентсвию
     * @param $pointType = {up | down} - тип точки(восход|закат)
     */
    private function tuningDoPoint($pointType) {
        $pointVect = ($pointType === 'up') ? $this->up : $this->down ;  // начальное состояние
//     догонялки
        $rPlay = $this->playingCatchUp($pointType);     // догонялки

        $pointVect['dtsMin'] = $rPlay['dtsSec'] / 60;    // минут
        $pointVect['psiGrad'] = $rPlay['psiGrad'];
        $pointVect['theta'] = $rPlay['theta'];     // конечное положение плоскости(рад)
        $time0Format = $this->toHour($pointVect['dts0min']);    // начальное приближение по
        $timeFormat = $this->toHour($pointVect['dtsMin']);
        $pointVect['time0Format'] = $time0Format ;
        $pointVect['timeFormat'] = $timeFormat ;

        $pointVect['point'] = $rPlay['point'] ;

        $tsStartMin = $this->p0['ts'] / 60 ; //+24*3600;
//        $tsMinStart = $tsStart/60 ;
//    корректировка по долготе
        $secFlag = true ;

        $time0Long = $this->longitudeTime(
            $tsStartMin + $pointVect['dts0min']);
        $timeLong = $this->longitudeTime(
            $tsStartMin + $pointVect['dtsMin']);
        $time0LongSec = $time0Long * 60 ;
        $timeLongSec = $timeLong * 60 ;

        $pointVect['time0LongFormat'] = $this->decomposeDate($time0LongSec,true) ;
        $pointVect['timeLongFormat'] = $this->decomposeDate($timeLongSec,true);


        //    корректировка по равноденствию
        $tCorrSec = ($pointType === 'up') ? $this->corrTime['dUpSec'] : $this->corrTime['dDnSec'] ;
        $timeCorrMin = $tCorrSec / 60;

        $pointVect['timeCorrMin'] = $timeCorrMin ;
        $pointVect['timeCorrFormat'] = $this->decomposeDate($timeLongSec + $tCorrSec,true);

        return $pointVect ;
    }

    /**
     * расчёт общей продолжительности
     */
    private function tuningDoTotal() {
        $dayTime0 = $this->toHour($this->down['dts0min'] -
            $this->up['dts0min']);
// учитывать p0
        $dtsMinP0up = $this->up['p0']['ts'] / 60 ;
        $dtsMinP0down = $this->down['p0']['ts'] / 60 ;
        $dayTimeTuning = $this->toHour(
            ($dtsMinP0down + $this->down['dtsMin']) -
            ($dtsMinP0up + $this->up['dtsMin']));
        //     корректировка на факт. равноденствия
        $timeDnCorrMin = $this->down['timeCorrMin'] ;
        $timeUpCorrMin = $this->up['timeCorrMin'] ;
        $dayTimeCorr = $this->toHour($timeDnCorrMin -
            $timeUpCorrMin);
        return [
            'dayTime0' => $dayTime0,
            'dayTimeTuning' => $dayTimeTuning,
            'dayTimeCorr' => $dayTimeCorr,
        ] ;





}
    /**
     * обнуление компонентов для вектора состояния точки(восхода/заката)
     * @param $pointVect
     */
    private function polarState($pointVect) {
        $emptTime = ['h' => '00', 'm' => '00', 's' => '00'] ;
        $pointVect['dtsMin'] = 0 ;
        $pointVect['time0'] = $emptTime ;
        $pointVect['timeLong'] = $emptTime ;
        $pointVect['time'] = $emptTime ;
        $pointVect['timeCorr'] = $emptTime ;
        return $pointVect ;
    }

    /**
     * общая продолжительность для полярного дня/ночи
     * @return array
     */
    private function polarDayTotal() {
        $dF = ($this->polarDay) ? ['h'=>'24','m'=>'00','s'=>'00'] :  ['h'=>'00','m'=>'00','s'=>'00'];
        return [
            'dayTime0' => $dF,
            'dayTimeTuning' => $dF,
            'dayTimeCorr' => $dF,
        ];
    }


    /**
     * поправка на долготу
     * @param $t - минут
     * @return float|int
     */
    private function longitudeTime($t = 0) {
        $deltaMin = self::MINUTES_IN_DEGREE * $this->longitudeAngle ;
        return $t - $deltaMin ;
    }
    private function toHour($tMin) {
//        if ($tMin < 0) {
//            $tMin = 24*60 + $tMin ;
//        }
        $t = $tMin / 60 ;
        $h = floor($t) ;
        $a = ($t - $h) * 60 ;
        $m = floor($a) ;
        $s = ($a - $m) * 60 ;

        return [
            'h' => $h,
            'm' => $m ,
            's' => round($s,0),
        ] ;
    }

    /**
     * игра в догонялки
     * @param string $type = {"up"|'down"} - тип события (восход/закат)
     * @return array
     */
    private function playingCatchUp($type = 'up') {
        // начальное приближение времени события (мин)
        $orbitObj = $this->orbitObj ;
        $lsObj  = $this->lsObj ;


//        $tsPlane = $tsPlane0 ;
//        $dtsPoint = $dts0 ;
        $eps = $this->timeEpsilon ;
        $tsFlag = true ;
        $noDeltaTheta = false;      //true ;// во избежании двойного вычитания
        $r = [] ;

        //        $theta = $this->theta0 ;
//        $psiGrad = ($type === 'up') ? $this->up['psiGrad'] :
//            $this->down['psiGrad'] ;
        $tsPlane0 = $this->p0['ts'] ;
        $psi0 = $this->p0['psi'] ;

        $dtsPrev = null ; // $this->dtsUpDn ;
        $tsPlane = 0 ;
        $dtsPoint = 0 ;
        $i = 0 ;
        while (  abs(($tsPlane - $tsPlane0) - $dtsPoint) > $eps ) {
            $tsPlane = $tsPlane0 + $dtsPoint ;
            $theta = $orbitObj->getTheta($tsPlane,$tsFlag,$noDeltaTheta) ; // - центральный угол точки орбиты
            $r = $lsObj->intersectPointsClc($theta + $this->moonTheta0) ;

            $dTs = $this->dtsClc($psi0,$r['psiInterval'],$dtsPrev) ;
            $dtsPrev = $dTs ;     // сохранить текущий
            $dtsPoint = (($type === 'up') ? $dTs['up']['min'] :
                $dTs['dn']['min']) * 60 ;

            $psiGrad = ($type === 'up') ? $dTs['up']['grad'] : $dTs['dn']['grad'] ; ;
            $i++ ;
        }
        if (sizeof($r) > 0) {
            $point = ($type === 'up') ? $r['p1'] : $r['p2'] ;
        } else {
            $point = ($type === 'up') ? $this->pDl['p1'] : $this->pDl['p2'] ;
        }
        $this->dtsUpDn  = $dtsPrev ;
        return [
            'theta' => $theta + $this->moonTheta0,
            'psiGrad'   => $psiGrad,
            'dtsSec' => $dtsPoint,   // уточнённое
            'eps' => $eps,
            'point' => $point,

        ] ;
    }
}