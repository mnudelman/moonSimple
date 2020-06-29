<?php


class Common
{
    const PLANET_ID_EARTH = 0 ;
    const PLANET_ID_MOON = 1;
    const ORBIT_TYPE_CIRCLE = 0;
    const ORBIT_TYPE_ELLIPT = 1;
    const  POINT_TYPE_SUNRISE = 0 ;    // точка восхода
    const  POINT_TYPE_SUNDOWN = 1 ;    // точка заката
    const  MINUTES_IN_DEGREE = 4 ; // число минут в 1 град при суточном вращении Земли
    const DAY_TIME_DARK = 'd' ;    // тёмное время суток
    const DAY_TIME_LIGHT = 'l' ;   // светлое время суток
    const POLAR_STATE_LIGHT = 0 ;  // состояние "полярный день"
    const POLAR_STATE_DARK = 1 ;  // состояние "полярная ночь"
    protected $AXIAL_TILT = 23.437 ; // наклон земной оси град
    //---------------------------------------------//
public function __construct()
{
    $this->AXIAL_TILT = $this->AXIAL_TILT/180 * pi() ;  // наклон оси (рад)
}

    /**
     * массив ['y'-> ...,'m'-> ...,'d'-> ...,'h'-> ..,'i'-> ...,'s'-> ..]
     * преобразовать в строку - дату
     * @param $dF
     * @return string
     */
    protected function dateFormatToDate($dF) {
        return $dF['y'] . '-' . $dF['m'] . '-' . $dF['d'] . ' ' .
            $dF['h'] . ':' . $dF['i'] . ':' . $dF['s'] ;
}

    /**
     * массив ['y'-> ...,'m'-> ...,'d'-> ...,'h'-> ..,'i'-> ...,'s'-> ..]
     * преобразовать в ts - timestamp
     * @param $dF
     * @return false|int
     */
protected function dateFormatToTs($dF) {
        return strtotime($this->dateFormatToDate($dF)) ;
}
    public function decomposeDate($dateT,$tsFlag = false) {
    $tStr = ($tsFlag) ? $dateT : strtotime($dateT) ;
    $dt = new DateTime() ;
//    $dt->setTimezone(new DateTimeZone('UTC')) ;
//    $dt->setTimezone(new DateTimeZone('Etc/GMT-5')) ;
//    $dt->setTimezone(new DateTimeZone('Asia/Yekaterinburg')) ;

    $dt->setTimestamp($tStr) ;

//    $z =  $dt->getTimezone() ;
//    var_dump($z) ;

    $y = $dt->format('Y') ;
    $m = $dt->format('n') ;
    $d = $dt->format('d') ;
    $h = $dt->format('H') ;
    $i = $dt->format('i') ;
    $s = $dt->format('s') ;
    return [
        'y' => $y - 0,
        'm' => $m - 0,
        'd' => $d - 0,
        'h' => $h - 0,
        'i' => $i - 0,
        's' => $s - 0,
    ] ;
}
    /**
     * преобразовать координаты из
     * локальной Phi (двумерная o1xy) в oX1Y1Z1
     * для памяти. Полные формулы включают R - радиус Земли
     * r - радиус сечения Pphi => r = Rcos(phi);
     * h = Rsin(phi) - расстояние от центра до сечения Pphi
     * дальше x = rcos(psi) ; y = rsin(psi), где psi - центральный угол
     * из центра o1 в точку. rcos(psi), rsin(psi) - это и есть локальные
     * координаты сечения Pphi => в системе oX1Y1Z1 будет:
     * x = Rcos(phi)cos(psi)  - cos(psi) - это локальный x
     * y = Rcos(phi)sin(psi)  - sin(psi) - это локальный y
     * z = h = Rsin(phi) ;
     * Во всех расчётах R присутствует в правой и левой частях =>
     *  можно сократить или (что тоже самое) R = 1;
     *
     */
    protected function transFromPhiLocToX1Y1Z1($x, $y)
    {
        $cosPhi = cos($this->anglePhi);
        $sinPhi = sin($this->anglePhi);
        $x = $x * $cosPhi;
        $y = $y * $cosPhi;
        $z = $sinPhi;
        return ['x' => $x, 'y' => $y, 'z' => $z];
//        return $this->transToX1Y1Z1($x,$y,$z) ;
    }

    /*
     * преобразовать координаты из системы oX1Y1Z1  в oXYZ
     */
    protected function transToXYZ($x1, $y1, $z1) {
        $cosA = cos($this->AXIAL_TILT) ;
        $sinA = sin($this->AXIAL_TILT) ;
        $x = $x1 * $cosA - $z1 * $sinA ;
        $y = $y1 ;
        $z = $x1 * $sinA + $z1 * $cosA ;
        return ['x' => $x, 'y' => $y, 'z' => $z] ;
    }

    /*
     * преобразовать координаты из системы oXYZ в oX1Y1Z1
     */
    protected function transToX1Y1Z1($x,$y,$z) {
        $cosA = cos($this->AXIAL_TILT) ;
        $sinA = sin($this->AXIAL_TILT) ;
        $x1 = $x * $cosA + $z * $sinA ;
        $y1 = $y ;
        $z1 = $z * $cosA - $x * $sinA ;
        return ['x' => $x1, 'y' => $y1, 'z' => $z1] ;
    }
    /*
     * угол между двумя векторами
     */
    protected function angleBetweenVectors($v1,$v2) {
        $modV1 = sqrt($v1['x'] ** 2 + $v1['y'] ** 2 + $v1['z'] ** 2);
        $modV2 = sqrt($v2['x'] ** 2 + $v2['y'] ** 2 + $v2['z'] ** 2) ;
//        echo 'modV1,modV2: '. $modV1 . ' ;' . $modV2 .'<br>' ;
        if (round($v1['x'],8) === round($v2['x'],8) &&
            round($v1['y'],8) === round($v2['y'],8) &&
            round($v1['z'],8) === round($v2['z'],8))
        {
            $cosAangle = 1 ;
        } else {
            $cosAangle =
                ($v1['x'] * $v2['x'] + $v1['y'] * $v2['y'] +$v1['z'] * $v2['z']) /
                ($modV1 * $modV2) ;
        }
//        echo 'angle:' .$angle .'<br>' ;
        $angle = acos($cosAangle) ;
        return ['angle' => $angle,'cosA' => $cosAangle] ;
    }

    /**
     * Векторное произведение
     * @param $a
     * @param $b
     * @return array
     */
    protected function vectorProduct($a,$b)
    {
        return [
            'x' => $a['y'] * $b['z'] - $a['z'] * $b['y'],
            'y' => -($a['x'] * $b['z'] - $a['z'] * $b['x']),
            'z' => $a['x'] * $b['y'] - $a['y'] * $b['x'],
        ];


    }

    /**
     * определить угол через вект произведение
     * @param $a
     * @param $b
     */
    protected function angleVectorProduct($a,$b) {
        $c = $this->vectorProduct($a,$b) ;
        $ma = $this->modVect($a) ;
        $mb = $this->modVect($b) ;
        $mc = $this->modVect($c) ;
        $sinA = $mc / ($ma * $mb) ;
        $angle = asin($sinA) ;
        $angleGrad = $angle / pi() * 180 ;
        return ['sinA' => $sinA, 'angle' => $angle,'angleGrad' => $angleGrad] ;

    }
    protected function modVect($a) {
        return sqrt($a['x'] ** 2 +  $a['y'] ** 2 + $a['z'] ** 2) ;
    }
    public function minutesToHour($minutes) {
        $s = ($minutes < 0) ? -1 : 1 ;
        $minutes = abs($minutes) ;
        $h = floor($minutes/60) ;
        $m = $minutes - 60 * $h ;
        return ['h' => $h,'m' => $m,'sign' => $s] ;
    }

    /**
     * форматирование php функции date_sun_info()
     * @param $date     - датаВремя
     * @param $latitude   - широта
     * @param $longitude  - долгота
     * @param bool $tsFlag  - true -> дата уже в формате timestamp
     * @return array
     */
    protected function dateSunInfo($date,$latitude ,$longitude,$tsFlag = false) {
        $ts = ($tsFlag) ? $date : strtotime($date) ;
        $dSI = date_sun_info ( $ts ,$latitude ,$longitude) ;
        $r = ['dsi' => $dSI, 'format' => []] ;
        foreach ($dSI as $key => $val) {
            $r['format'][$key] = date("H:i:s", $val) ;
        }
        return $r ;
    }

    /**
     * интервал [-2pi,2pi]
     * @param $a
     * @param bool $gradFlag
     * @return float|int
     */
    protected function normalizeAngle($a,$gradFlag = false) {
        $a = ($gradFlag) ? $a / 180 * pi() : $a ;
        $sign = ($a >= 0) ? 1 : -1 ;
        $a = abs($a) ;
        $pi2 = 2 * pi() ;
        while ($a > $pi2) {
            $a -= $pi2 ;
        }
        $a = $sign * $a  ;
        return $a ;
    }
    protected function signCompare($x,$y) {
        return ($x > 0 && $y > 0) ||
            ($x == 0 && $y == 0) ||
            ($x < 0 && $y < 0) ;
    }

}