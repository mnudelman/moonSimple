<?php


//namespace montenbruck;
//use \montenbruck\Mat3D ;
//use Common ;
class CoordinateSystem extends montenbruck\Mat3D
{
    private $equCo = [   // экваториальные координаты
        'dec' => 0,      // склонение [рад]
        'tau' => 0,      // часовой угол [рад]
        'lat' => 0,      // географическая широта [рад]
    ] ;
    private $horCo = [    // горизонтальные координаты
        'Az' => 0,        // азимут [рад]
        'h'  => 0,        // высота [рад]
    ] ;
    private $dTime = '2020-05-9 20:00:00' ;     // дата - время
    private $dTFormat= [] ;           // компоненты даты-времени
    private $timeZone = 0 ;           // сдвиг по отношению к Гринвичу
    private $jdTCenture = 0.0 ;       // время в юлианских столетиях от эпохи J2000
    private $jdTime ;
    private $mjdTime ;
    private $dayOfY ;
    private $spaceObjId ;
    private $latitude = 0.0 ;        // широта точки наблюдения
    private $longitude = 0.0 ;       // долгота
    public function __construct()
    {
        parent::__construct();
        $this->spaceObjId = Common::OBJECT_ID_MOON ;
    }

    public function setTime($t,$tZone = 0)
    {
//        $co =  $this->comObj ;
        $this->dTime = $t;
        $this->timeZone = $tZone ;
        $this->dTFormat = $this->decomposeDate($t);
        $y = $this->dTFormat['y'];
        $m = $this->dTFormat['m'];
        $d = $this->dTFormat['d'];
        $h = $this->dTFormat['h'];
        $i = $this->dTFormat['i'];
        $s = $this->dTFormat['s'];
        $this->mjdTime = $this->calenDate2mjd($y,$m,$d,$h,$i,$s) ;
        $this->jdTime = $this->mjd2jd($this->mjdTime) ;
        $this->jdTCenture = $this->jdCentury($this->jdTime) ;
        $this->dayOfY = $this->dayOfYear($this->dTFormat) ;
        $ts = strtotime($t) ;
        $tjd = unixtojd($ts) ;
//        echo 'tjdFromTimestamp: ' . $tjd . '<br>' ;
//        echo 'jdTime: ' . $this->jdTime  . '<br>' ;
//        echo 'floor(jdTime): ' . floor($this->jdTime)  . '<br>' ;
//        echo 'nSecs: ' . ($this->jdTime - floor($this->jdTime))* $this->SECS . '<br>' ;
        return $this ;
    }
    private function dayOfYear($dtF) {
        $y = $dtF['y'];
        $m = $dtF['m'];
        $d = $dtF['d'];
        $h = $dtF['h'];
        $i = $dtF['i'];
        $s = $dtF['s'];
        $ts = mktime(0,0,0,$m,$d,$y) ;
// get day of year for 08 aug 2008
// result 221
        $dOfY = date("z", $ts) + 1;
        return $dOfY ;

    }

    /**
     * Географические координаты точки
     * @param $lat
     * @param $long
     * @param bool $radFlag
     * @return $this
     */
    public function setPoint($lat, $long, $radFlag = false) {
        $this->latitude = ($radFlag) ? $lat : deg2rad($lat) ;
        $this->longitude = ($radFlag) ? $long : deg2rad($long) ;
        return $this ;
    }
    public function setPlanet($pId)
    {
        $this->spaceObjId = $pId ;
        return $this ;
    }

    public function getCoordinate($coSystemId)
    {

    }

    /**
     * преобразование координат из эклиптической в экваториальную систему
     * @param $rEcl - вектор [x,y,z]
     * @return  $rOut - [xOut,yOut,zOut] - вектор
     */
    public function ecl2equ($rEcl) {
        $rVectIdFrom = 'r_ecl' ;
        $rVectOutIdTo = 'r_equ' ;

        $eps = $this->clcEpsFromT() ;

        $this->matrRx($eps)
        ->newMatr($rVectIdFrom,3,1,'эклипт система')
        ->addCol($rVectIdFrom,0,$rEcl) ;
        $this->matrMult('Rx',$rVectIdFrom,$rVectOutIdTo) ;
        $rOut = $this->getVector($rVectOutIdTo);
        return $rOut ;
    }

    /**
     * наклон земной оси по отношению к плоскости эклиптики
     * @return float|int
     */
    private function clcEpsFromT() {
        $t = $this->jdTCenture ;       // юлианских столетий от эпохи J2000
        $eps = (23.43929111 -
            (46.8150 + (0.00059 - 0.001813 * $t)* $t) * $t/3600.0) * $this->RAD ;
        return $eps ;
    }
    /**
     * Преобразование экваториальных координат в эклиптические
     * @param $rEqu - вектор [x,y,z]
     * @return $rOut  - [xOut,yOut,zOut] - вектор
     */
    public function equ2ecl($rEqu) {
        $rVectId = 'r_equ' ;
        $rVectOutId = 'r_ecl' ;
        $eps = $this->clcEpsFromT() ;
        $this->matrRx(-$eps)
            ->newMatr($rVectId,3,1,'экваториальная система')
            ->addCol($rVectId,0,$rEqu) ;
        $this->matrMult('Rx',$rVectId,$rVectOutId) ;
        $rOut = $this->getVector($rVectOutId);
        return $rOut ;

    }

    /**
     * Преобразование экваториальных координат в горизонтальные(стр 51)
     * всё в радианах
     * @param $dec   - склонение [рад]
     * @param $tau   - часовой угол [рад]
     * @param $lat   - географическая широта [рад]
     * @return array - ['az' => азимут,'h' => высота над горизонтом]
     */
    private function equ2hor($dec,$tau,$lat) {
        $r = 1 ;
        $polarCoord = [$tau,$dec,$r] ;
        $polarFlag = true ;
        // через newVector создаётся две матрицы 1 * 3 :
        // $id - декартовы координаты ; $id . '_polar' - полярные координаты
        $this->newVector('eEqu',$polarCoord,$polarFlag) ;
        $this->matrRy(pi() / 2 - $lat) ;
        $this->trans('eEqu','eEqu') ;

        $this->matrMult('Ry','eEqu','eHor') ;

        $eHorMatr = $this->getMatr('eHor') ;
        $eHorTab = $eHorMatr['tab'] ;
        $x = $eHorTab[0][0] ;
        $y = $eHorTab[1][0] ;
        $z = $eHorTab[2][0] ;

        $coord = [$x,$y,$z] ;
        $this->newVector('eHor',$coord) ;
        $eHorab = $this->getVector('eHor',true) ;    // полярные координаты
//        $eMoonTab = $eMoon['tab'] ;
        $az = $eHorab[0] ;        // прямое восхождение
        $h = $eHorab[1] ;       // склоненние

        return ['az' => $az,'h' => $h] ;
    }

    /**
     * Преобразование горизонтальных координат в экваториальные
     * @param $h     - высота над горизонтом [рад]
     * @param $az    - азимут [рад]
     * @param $lat   - географ широта  [рад]
     * @return array - ['dec' => склонение,'tau' => часовой угол] ;
     */
    private function hor2equ($az,$h,$lat) {
        $r = 1 ;
        $polarCoord = [$az,$h,$r] ;
        $polarFlag = true ;
        // через newVector создаётся две матрицы 1 * 3 :
        // $id - декартовы координаты ; $id . '_polar' - полярные координаты
        $this->newVector('eHor',$polarCoord,$polarFlag) ;
        $this->matrRy(-(pi() / 2 - $lat)) ;

        $this->matrMult('Ry','eHor_polar','eEquPolar') ;
//    надо сделать
        $equMatr = $this->getMatr('eEquPolar') ;
        $equTab = $equMatr['tab'] ;
        return ['tau' => $equTab[0],'dec' => $equTab[1]] ;
    }

    /**
     * Расчёт экваториальных координат из эклиптических прямым расчётом
     * (без использования матричных преобразований ) из
     * astronomical almanac 1997
     * @param $lambda    - эклиптическая широта
     * @param $beta      -               долгота
     */
    private function eql2equ1($lambda,$beta)
    {
        //  экваториальные координаты
        $l = cos(deg2rad($beta)) * cos(deg2rad($lambda));
        $m = 0.9175 * cos(deg2rad($beta)) * sin(deg2rad($lambda))
            - 0.3978 * sin(deg2rad($beta));
        $n = 0.3978 * cos(deg2rad($beta)) * sin(deg2rad($lambda)) + 0.9175 * sin(deg2rad($beta));
        $a = $this->getAngl($l, $m);
        $alpha = $a['rad'];      // прямое восхождение
        $alphaDeg = $a['deg'] ;
        $aHour = $this->deg2hour($alphaDeg) ;    // в часовой формат
        $delta = asin($n);       // склонение
        $deltaDeg = rad2deg($delta) ;
        return [
            'rad' => ['alpha' => $alpha, 'delta' => $delta],
            'deg' => ['alpha' => $alphaDeg, 'delta' => $deltaDeg],
            'hour' => ['alpha' => $aHour['h'] . ':' . $aHour['m'] . ':' .$aHour['s']],
        ] ;
    }

    /**
     * преобразовать экваториальные координаты в горизонтальные
     *  прямым расчётом (без использования матричных преобразований ) из
     * astronomical almanac 1997
     * горизонтальные координаты с нулём азимута на СЕВЕР
     * @param $theta0  - звёздное время
     * @param $alpha   - прямое восхождение
     * @param $delta   - склонение
     * @param $lat     - географическая широта
     * все параметры в радианах
     */
    private function equ2hor1($theta0,$alpha,$delta,$lat) {
        $theta0Deg = rad2deg($theta0) ;
        $alphaDeg = rad2deg($alpha) ;
        $tauDeg = $theta0Deg - $alphaDeg ;   // градусы часового угла
        $tauRad = deg2rad($tauDeg) ;
        $sinH = sin($delta) * sin($lat) + cos($delta) * cos($tauRad)*cos($lat) ;
        $h = asin($sinH) ;
        $sinAz = -cos($delta) * sin($tauRad)  ; //   /cos($h) ;
        $cosAz = (sin($delta) * cos($lat) -
            cos($delta) * cos($tauRad) * sin($lat)) ; //  /cos($h) ;
        $a = $this->getAngl($cosAz,$sinAz) ;
        $azDeg = $a['deg'] ;
        $azRad = $a['rad'] ;
        $hDeg = rad2deg($h) ;
        return ['rad' => ['az' => $azRad, 'h' => $h],
        'deg' => ['az' => $azDeg, 'h' => $hDeg],
        ] ;
    }
    /**
     * преобразовать угол(градусы) в часовой формат
     * 1h = 15deg
     * @param $angleDeg
     */
    private function deg2hour($angleDeg) {
        $alphaHour = $angleDeg / 15 ;
        $h = floor($alphaHour) ;
        $minutes = $this->frac($alphaHour) * 60 ;
        $m = floor($minutes) ;
        $s = floor($this->frac($minutes) * 60) ;
        return ['h' => $h, 'm' => $m, 's' => $s] ;
   }
    /**
     * Экваторальные координаты Луны
     *    - время в юлианских столетиях от эпохи J2000
     * время определяется один раз через setTime()
     * @return array ['ra'=> прямое восхождение,'dec' => склонение] ;
     */
    public function miniMoon() {
        $t = $this->jdTCenture ;
        $mjd = $this->mjdTime ;
        $phi = $this->latitude ;       // широта
        $lambda = $this->longitude ;   // долгота
        //  Средние элементы лунной орбиты
        $pi2 = 2 * pi() ;
        // средняя долгота в полных оборотах
        $l0 = $this->frac(0.606433 + 1336.855225 * $t) ;
        // Средняя аномалия Луны
        $l = $pi2 * $this->frac(0.374897 + 1325.552410 * $t) ;
        // Средняя аномалия Солнца
        $ls = $pi2 * $this->frac(0.993133 + 99.997361 * $t) ;
        // Разница долгот Луна - Солнце
        $d = $pi2 * $this->frac(0.827361 + 1236.853086 * $t) ;
        // Расстояние от восходящего узла
        $f = $pi2 * $this->frac(0.259086 + 1342.227825 * $t) ;
        // Возмущения в долготе и широте
        $dL = 22640 * sin($l) - 4586 * sin($l - 2*$d) + 2370 * sin(2*$d)
        + 769 * sin(2*$l)
        - 668 * sin($ls) - 412 * sin(2*$f) - 212 * sin(2*$l - 2*$d)
        - 206 * sin($l + $ls - 2*$d)
        + 192 * sin($l + 2*$d) - 165 * sin($ls - 2*$d) - 125 * sin($d)
        -110 * sin($l + $ls)
        + 148 * sin($l - $ls) - 55 * sin(2*$f - 2*$d) ;
        $s = $f + ($dL + 412 * sin(2*$f) + 541 * sin($ls)) / $this->ARCS ;
        $h = $f - 2*$d ;
        $n = -526 * sin($h) + 44 * sin($l +$h)
            - 31 * sin(-$l + $h) - 23 * sin($ls + $h)
            + 11 * sin(-$ls + $h) - 25 * sin(-2 * $l + $f)
            +21 * sin(-$l + $f) ;
        // эклиптические долгота и широта [рад]
        $lMoon = $pi2 * $this->frac($l0 + $dL / (1296.0 * 10 ** (3))) ;
        $bMoon = (18520.0 * sin($s) + $n) / $this->ARCS ;
        // Экваториальные координаты

        $r = 1 ;
        $eps = $this->EPS ;
        $polarCoord = [$lMoon,$bMoon,$r] ;
        $polarFlag = true ;
        $this->newVector('eEql',$polarCoord,$polarFlag) ;
        $this->matrRx(-$eps) ;
//        $this->trans('eEql_polar','eEql_polar') ;
        $this->trans('eEql','eEql') ;
        $this->matrMult('Rx','eEql','eMoon') ;
        $eMoon = $this->getMatr('eMoon') ;
//        return ['az' => $eHor[0],'h' => $eHor[1]] ;
// теперь это экваториальные координаты. Переводим в полярные
        $eMoonTab = $eMoon['tab'] ;
        $x = $eMoonTab[0][0] ;
        $y = $eMoonTab[1][0] ;
        $z = $eMoonTab[2][0] ;

        $coord = [$x,$y,$z] ;
        $this->newVector('eEqu',$coord) ;
        $eMoonTab = $this->getVector('eEqu',true) ;    // полярные координаты
        $ra = $eMoonTab[0] ;        // прямое восхождение
        $dec = $eMoonTab[1] ;       // склоненние

//      переходим в горизонтальную систему (стр 54)
        $gmst = $this->gmstClc($mjd) ;
        $tau = $gmst - $ra ;
// прямой расчёт по формулам преобразования для азимута с нулём на ЮГ
// расчёт через преобразование системы координат. Из экваториальной в
//       горизонтальную
        $eHor = $this->equ2hor($dec,$tau,$phi) ;

        $az = $eHor['az'] ;
        $h =  $eHor['h'] ;
        return [
            'latitude' => $phi,     // широта
            'longitude' => $lambda,     // долгота
            'eql' => ['lambda' => $lMoon,'beta' => $bMoon],
            'equ' => ['ra'=> $ra,'dec' => $dec,'tau' => $tau],
            'hor' => ['az' => $az,'h' => $h],
            'grad' => [
                'latitude' => rad2deg($phi),     // широта
                'longitude' => rad2deg($lambda),     // долгота
                'eql' => ['lambda' => rad2deg($lMoon),
                    'beta' => rad2deg($bMoon)],
                'equ' => ['ra'=> rad2deg($ra),
                    'dec' => rad2deg($dec),
                    'tau' => rad2deg($tau)],
                'hor' => ['az' => rad2deg($az),
                    'h' => rad2deg($h)],
            ],
        ] ;
    }

    /**
     * Расчёт азимута, высоты Луны по алгоритму astronomical almanac 1997
     * @return array
     */
    public function miniMoon1()
    {
// astronomical almanac for the year 1997
//  результат, совпадающий с miniMoon
// https://babel.hathitrust.org/cgi/pt?id=uc1.31822016402356&view=1up&seq=238
//  D46
//      The following formulae give approximate
//       geocentric coordinates of the Moon.
//       The errors will rarely exceed
//       0.3 degree in ecliptic longitude(lambda),
//       0.2 deg in ecliptic latitude(beta),
//       0.003 deg in horizontal parallax(Pi),
//       0.001 deg in semidiameter(SD),
//       0.2 Earth radii in distance(r),
//       Горизонтальные координаты
//       0.3 deg in right ascension(alpha) and
//       0.2 deg in declination(delta)
//       On this page the time argument T is the number of
//       Julian centuries from J2000-
//        T = (JD – 2451545.0)/36525 = (–1096.5+dayofyear+UT/24)/36525
//        UT = (T * 36525 + 1096.5 - dayofyear) * 24 ; // часов
//where day of year is given on pages B2–B3 and
// UT is the universal time in hours.
//lambda = 218.32+481267.883*T
//          +6.29*sin(134.9 + 477198.85*T)
//              –1.27*sin(259.2–413335.38*T)
//          +0.66*sin (235.7 + 890534.23*T)
//              +0.21*sin(269.9 + 954397.70*T)
//          — 0.19sin(357.5 + 35999.05*T)
//               –0.11*sin(186.6 + 966404.05*T) ;
//  beta = +5.13*sin(93.3+ 483202.03*T)
//             + 0.28*sin(228.2 + 960400.87*T)
//         — 0.28*sin(318.3 + 6003.18*T)
//              — 0.17*sin(217.6 – 407332.20*T);
//   Pi =+ 0.9508
//       + 0.0518*cos(134.9 + 477198.85*T)
//              +0.0095*cos(259.2 – 413335.38*T)
//       + 0.0078*cos(235.7 + 890534.23*T)
//              + 0.0028*cos(269.9 + 954397.70*T) ;
//    SD = 0.2725* Pi;
//    r = 1/sin(Pi) ;
// Form the geocentric direction cosines(l,m,n)from
//    l = cos(beta) * cos(lambda) ;
//    m = 0.9175 * cos(beta) * sin(lambda) - 0.3978 * sin(beta) ;
//    n = 0.3978 * cos(beta) * sin(lambda) + 0.9175 * sin(beta) ;
// where
//    l = cos(delta) * cos(alpha)
//    m = cos(delta) * sin(alpha)
//    n = sin(delta)
// then
//    alpha = atan(m / l) ;
//    delta = asin(n) ;
//  The following formulae give approximate topocentric values
//  of rightascension(alpha1),declination(delta1),distance(r1),
//   parallax(Pi1) and semidiameter(SD1).
//   Form the geocentric rectangular coordinates(x,y,z)
//  from:x=r*l=r*cos(delta)*cos(alpha)
//       y=r*m=r*cos(delta)*sin(alpha).
//       z=r*n=r*sin(delta)
//  Form the topocentric rectangular coordinates(x',y',z')
//  from:x1 = x – cos(phi1) * cos(theta0) ;
//       y1 = y – cos(phi1) * sin(theta0) ;
//       z1 = z – sin(phi1) ;
//  where phi1 is the observer's geocentric latitude and
//       theta0 is the local sidereal time
//       theta0 = 100.46 + 36000.77 * T + lambda1 + 15 * UT ;
//  where lambda1 is the observer's east longitude.
//  Then
//       r1 = sqrt((x1**2 + y1**2 + z1**2)) ;
//       alpha1 = atan(y1/x1) ;
//       delta1 = asin(z1/r1) ;
//       Pi1 = asin(1/r1) ;
//       SD1 = 0.2725*Pi1 ;
        $jd = $this->jdTime;
        $mjd = $this->mjdTime;
        $lat = $this->latitude;       // широта
        $long = $this->longitude;   // долгота
        $t = ($jd - 2451545.0) / 36525;
//      эклиптические координаты
        $lambda = 218.32 + 481267.883 * $t
            + 6.29 * sin(deg2rad(134.9 + 477198.85 * $t))
            - 1.27 * sin(deg2rad(259.2 - 413335.38 * $t))
            + 0.66 * sin(deg2rad(235.7 + 890534.23 * $t))
            + 0.21 * sin(deg2rad(269.9 + 954397.70 * $t))
            - 0.19 * sin(deg2rad(357.5 + 35999.05 * $t))
            - 0.11 * sin(deg2rad(186.6 + 966404.05 * $t));
        $beta = +5.13 * sin(deg2rad(93.3 + 483202.03 * $t))
            + 0.28 * sin(deg2rad(228.2 + 960400.87 * $t))
            - 0.28 * sin(deg2rad(318.3 + 6003.18 * $t))
            - 0.17 * sin(deg2rad(217.6 - 407332.20 * $t));
        $Pi =+ 0.9508
       + 0.0518 * cos(deg2rad(134.9 + 477198.85 * $t))
              +0.0095 * cos(deg2rad(259.2 - 413335.38 * $t))
       + 0.0078 * cos(deg2rad(235.7 + 890534.23 * $t))
              + 0.0028 * cos(deg2rad(269.9 + 954397.70 * $t)) ;
        $r = 1/sin(deg2rad($Pi)) ;
        $sd = 0.2725 * $Pi ;
//  экваториальные координаты
//============================================================
    $rEqu = $this->eql2equ1($lambda,$beta) ;
//============================================================
    //      звёздное время
    $theta0 = $this->gmstClc($mjd) ;
//    горизонтальные координаты с нулём азимута на СЕВЕР
    $alphaRad = $rEqu['rad']['alpha'] ;      // прямое восхождение
    $deltaRad = $rEqu['rad']['delta'] ;      // склонение
    $rHor = $this->equ2hor1($theta0,$alphaRad,$deltaRad,$lat) ;
//======================================================================
    $alphaH = $rEqu['deg']['hour'] ;    // угол в часовой мере
    $deltaDeg = $rEqu['deg']['delta'] ;
    $deltaD = floor($deltaDeg) ;        // выделить град : минуты
    $deltaM = floor($this->frac($deltaDeg) * 60) ;
    return [
        'rInEarthRadius' => $r,          // расстояние в долях земного радиуса
        'rKilometers' => $r * $this->earthRadius,
        'semidiameter' => $sd,           // полудиаметр град
        'eql' => [                    // эклиптическая система
            'lambda' => $lambda,
            'lambdaMod' => $this->modulo($lambda, 360),
            'beta' => $beta,
        ],
        'equ' => [                   // экваториальная система
            'alpha' => $rEqu['deg']['alpha'],   // rad2deg($alpha),
            'delta' => $rEqu['deg']['delta'],   //rad2deg($delta),
            'alphaH' => $alphaH['h'] .':'.$alphaH['m'] .':'.$alphaH['s'] ,   // $alphaH . ':' . $alphaM,
            'deltaDegFormat' => $deltaD . ':' . $deltaM,
        ],
        'hor' => [                  // горизонтальная
            'az' => $rHor['deg']['az'],       //$azDeg ,
            'h'  => $rHor['deg']['h'],       //$hDeg,
        ],
    ] ;
    }

    /**
     * по тексту из Монтенбрук .....
     * результат неверный (ошибку не нашёл)
     * @return array
     */
    public function miniSun()
    {
        $jd = $this->jdTime;
        $t = $this->jdTCenture;
        $mjd = $this->mjdTime;
        $phi = $this->latitude;       // широта
        $lambda = $this->longitude;   // долгота
        //  Средние элементы лунной орбиты
        $pi2 = 2 * pi();
        $m = $pi2 * $this->frac(0.993133 + 99.997361 * $t) ;
// эклиптическая долгота
        $l = $pi2 * $this->frac(0.7859453 + $m / $pi2 +
                (6893.0 * sin($m) + 72.0 * sin(2 * $m) + 6191.2 * $t) / (1296.0 * 1000) ) ;
    echo 'l-эклипт долгота: ' . $l . '<br>' ;
    $beta  = 0 ;
    $lambda = $l ;
        $n = $jd - 451545.0;       // n is the number of days from J2000.0.
        $lambdaRad = deg2rad($lambda);
        $epsilon = 23.439 - 0.0000004 * $n;                      // наклон орбиты Зимли к эклиптике
        $epsilonRad = deg2rad($epsilon) ;
        $alpha = atan(cos($epsilonRad) * tan($lambdaRad));

        $x = cos($lambdaRad);
        $y = cos($epsilonRad) * sin($lambdaRad);
        $a = $this->getAngl($x, $y);
        $alpha = $a['rad'];
        $delta = asin(sin($epsilonRad) * sin($lambdaRad));
        echo '<b>equ:alpha,delta::' . $alpha . '; ' . $delta . '</b><br>' ;


        //  экваториальные координаты
        $l = cos(deg2rad($beta)) * cos(deg2rad($lambda)) ;
        $m = 0.9175 * cos(deg2rad($beta)) * sin(deg2rad($lambda))
            - 0.3978 * sin(deg2rad($beta)) ;
        $n = 0.3978 * cos(deg2rad($beta)) * sin(deg2rad($lambda)) + 0.9175 * sin(deg2rad($beta)) ;
        $a = $this->getAngl($l,$m) ;
        $alpha = $a['rad'] ;

        $delta = asin($n) ;
//=============================================================================
        return [
            'eql' => [                    // эклиптическая система
                'lambda' => $lambda,
                'lambdaMod' => $this->modulo($lambda, 360),
                'beta' => $beta,
            ],
            'equ' => [                   // экваториальная система
                'alpha' => rad2deg($alpha),
                'delta' => rad2deg($delta),
            ],

        ];
    }

    /**
     * азимут, высота Солнца по алгоритму Astronomical almanac 1997
     * результат удовлетворительный(почти точное совпадение)
     * @return array[]
     */
    public function miniSun1()
    {
// astronomical almanac for the year 1997
//  результат, не(!) совпадающий с miniSun
// https://babel.hathitrust.org/cgi/pt?id=uc1.31822016402356&view=1up&seq=238
//  C24
//Low precision formulas for the Sun's coordinates and the equation of time
//The following formulas give the apparent coordinates of the Sun to a precision of 0°.01 and
// the equation of time to a precision of 0".1 between 1950 and2050;on this page the time
// argument n is the number of days from J2000.0.
//n=JD–2451545.0=–1096.5+day of year(B2—B3)+fraction of day from 0h UT
// Mean longitude of Sun, corrected for aberration: L=280°.472+0°.9856474n
//Mean anomaly: g= 357°.528+0°.9856003n
//Put L and g in the range 0° to 360° by adding multiples of 360°.
//Ecliptic longitude:  lambda = L+1°.915 * sin(g) + 0°.020 * sin(2g)
//Ecliptic latitude:   beta=0°
//Obliquity of ecliptic: epsilon =23°.439–0°.0000004n
//Right ascension (in same quadrant as lambda): alpha = tan^-1(cos(epsilon)tan(lambda))
//Alternatively, alpha may be calculated directly from
//alpha = lambda – f*t*sin(2*lambda) + (f/2)t^2 * sin(4lambda)
//      where f=180/pi  and t=tan^2(epsilon/2)
//Declination:delta = sin^-1(sin(epsilon) * sin(lambda))
//Distance of Sun from Earth,in au: R=1.00014–0.01671*cos(g) – 0.00014*cos(2g)
//Equatorial rectangular coordinates oft he Sun,in au:
//x = R*cos(lambda),   y = R*cos(epsilon) * sin(lambda),   z = R*sin(epsilon) * sin(lambda)
//Equation of time(apparent time minus mean time):
//        E,in minutes of time = (L – alpha),in degrees, multiplied by 4.
//Horizontal parallax: 0°.0024
//Semidiameter : 0°.2666/R
// Light time:0.0058 (????)
//-----------------------------------------------------------------------------------//
        $jd = $this->jdTime;
        $mjd = $this->mjdTime;
        $lat = $this->latitude;       // широта
        $long = $this->longitude;   // долгота
        $t = ($jd - 2451545.0) / 36525;
        $n = $jd - 2451545.0;       // n is the number of days from J2000.0.
        $l = 280.472 + 0.9856474 * $n;    // aberration:
        $g = 357.528 + 0.9856003 * $n;    // anomaly:

//Put $l and $g in the range 0° to 360° by adding multiples of 360°.
        $g = $this->modulo($g, 360);
        $l = $this->modulo($l, 360);
        $epsilon = 23.439 - 0.0000004 * $n;                      // наклон орбиты Зимли к эклиптике
        $gRad = deg2rad($g);
        $epsilonRad = deg2rad($epsilon);
// эклиптические координаты
        $lambda = $l + 1.915 * sin($gRad) + 0.020 * sin(2 * $gRad); //Ecliptic longitude
        $beta = 0;                                               //Ecliptic latitude
        $lambdaRad = deg2rad($lambda);

//================================================================================================================
//Distance of Sun from Earth,in au:
$dAU = 1.00014 - 0.01671*cos($gRad) - 0.00014 * cos(2*$gRad) ;
//        Semidiameter : 0°.2666/R   - угловой размер (полдиаметра) град
$semidiameter = 0.2666 / $dAU ;
//================================================================================================================
//  экваториальные координаты
//============================================================
        $rEqu = $this->eql2equ1($lambda,$beta) ;
//============================================================
        //      звёздное время
        $theta0 = $this->gmstClc($mjd) ;
//    горизонтальные координаты с нулём азимута на СЕВЕР
        $alphaRad = $rEqu['rad']['alpha'] ;      // прямое восхождение
        $deltaRad = $rEqu['rad']['delta'] ;      // склонение
        $rHor = $this->equ2hor1($theta0,$alphaRad,$deltaRad,$lat) ;
//======================================================================
        $alphaH = $rEqu['deg']['hour'] ;    // угол в часовой мере
        $deltaDeg = $rEqu['deg']['delta'] ;
        $deltaD = floor($deltaDeg) ;        // выделить град : минуты
        $deltaM = floor($this->frac($deltaDeg) * 60) ;
        return [
            'distanceAU' => $dAU,
            'semidiameter' => $semidiameter,
            'eql' => [                    // эклиптическая система
                'lambda' => $lambda,
                'lambdaMod' => $this->modulo($lambda, 360),
                'beta' => $beta,
            ],
            'equ' => [                   // экваториальная система
                'alpha' => $rEqu['deg']['alpha'],   // rad2deg($alpha),
                'delta' => $rEqu['deg']['delta'],   //rad2deg($delta),
                'alphaH' => $alphaH['h'] .':'.$alphaH['m'] .':'.$alphaH['s'] ,   // $alphaH . ':' . $alphaM,
                'deltaDegFormat' => $deltaD . ':' . $deltaM,
            ],
            'hor' => [                  // горизонтальная
                'az' => $rHor['deg']['az'],       //$azDeg ,
                'h'  => $rHor['deg']['h'],       //$hDeg,
            ],
        ] ;
    }
    /**
     * вычисление среднего гринвического звёздного времени
     *  по модифицированному юлианскому времени
     * @param $mjd
     * @return float  - среднее гринвическое звёздное время [рад]
     */
    protected function gmstClc($mjd)
    {
        $mjd0 = floor($mjd);    // на начало суток
        $secs = $this->SECS;
//        $ut = $secs * ($mjd - $mjd0) ;  //[sec]
//       ut здесь кол секунд текущих суток. Заменил на прямое вычисление
//       см. ниже
//    долготу переводим в секунды (1h = 15 град)
        $long = $this->longitude;
        $longDeg = rad2deg($long);
        $longSec = $longDeg / 15 * 3600;
        $tF = $this->dTFormat;
        $min = $tF['i'];
        $sec = $tF['s'];
        $hour = $tF['h'];

        $utSec = $hour * 3600 + $min * 60 + $sec;
// коэфф перед utSec - это звёздныйГод/СолнечныйГод (разница 1 сутки)
//        366.2422/365.2422
// начальное значение - это 6h41m50.54841sec ,
// т.е. угол на начало эры J2000.  Дальше идёт вычисление на тек дату.
//    скорее всего обрезали какое-то степенное разложение
//    GMST of 0h UT1 = 6h41m50.54841sec + 8640184.812866T + .....
        $t0 = ($mjd0 - 51544.5) / 36525.0; // кол столетий от эпохи J2000
        $gmst = 24110.54841 + 8640184.812866 * $t0 +
            1.0027379093 * $utSec
            + (0.093104 - 0.0000062 * $t0) * $t0 * $t0;  // сек
        $gmst = $gmst + $longSec;
        $theta0 = (2 * pi() / $secs) *
            $this->modulo($gmst, $secs);     //  рад
        return $theta0;
    }

    /**
     * Вычисление угла по координатам
     * @param $x
     * @param $y
     * @return array
     */
    private function getAngl($x,$y)  {
        $r = sqrt($x * $x + $y * $y) ;
        $pi = pi() ;
        $pi2 = 2 * $pi ;
        if ($r == 0) {
            return ['rad' => 0, 'deg' => 0] ;
        }
        $cosA = $x / $r ;
        $sinA = $y / $r ;
        if ($x > 0 && $y > 0) {
            $a = acos($cosA) ;
        }elseif ($x < 0 && $y > 0) {
            $a = acos($cosA) ;
        }elseif ($x < 0 && $y < 0 ) {
            $a = $pi + (-asin($sinA)) ;
        }elseif ($x > 0 && $y < 0) {
            $a = $pi2 + asin($sinA) ;
        }elseif ($x == 0 && $y == 0) {
            $a = 0.0 ;
        }elseif ($y = 0 ) {
            $a = ($x < 0 ) ? -$pi : $pi ;
        }elseif ($x = 0) {
            $a = ($y > 0) ? $pi / 2 : 1.5 * $pi ;
        }
        return ['rad' => $a,
            'deg' => rad2deg($a)] ;
}

}