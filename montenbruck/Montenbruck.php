<?php
/**
 * Class Montenbruck
 * по материалам книги Монтенбрук,Пфлегер "Астрономия на персональном компьютере"
 */
namespace montenbruck ;
use Common ;
class Montenbruck extends Common
{
//   константы
    protected $PI;
    protected $PI2;    // 2pi
    protected $RAD;    // pi/180
    protected $DEG;    // 180/pi
    protected $ARCS;   // 3600*180/pi ;
    protected $AU;     // 149597870 - астрономическая единица
    protected $C_LIGHT; // скорость света 173,14 а.е./день
    protected $epsGrad = 23.43929111;     // наклок оси Земли по отношению эклиптики
    protected $EPS;                     // в радианах
    protected $SECS ; // секунд в сутках
    protected $earthRadius = 6371 ;    // км
//------------------------------------------------------------
    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    /**
     *   установка констант
     */
    private function init()
    {
        $this->PI = pi();
        $this->PI2 = 2 * pi();
        $this->RAD = $this->PI / 180;
        $this->DEG = 180 / pi();
        $this->ARCS = 3600 * 180 / pi();
        $this->AU = 149597870.0;
        $this->C_LIGHT = 173.14;
        $this->EPS = $this->RAD * $this->epsGrad;
        $this->SECS = 86400.0 ;
    }

    /**
     * Дробная часть числа
     * @param $x
     * @return false|float
     */
    protected function frac($x)
    {
        return $x - floor($x);
    }

    /**
     * остаток от деления x / y
     * @param $x
     * @param $y
     * @return float|int
     */
    protected function modulo($x, $y)
    {
        return $y * $this->frac($x / $y);
    }

    protected function ddd($d, $m, $s)
    {
        $sgn = ($d < 0 || $m < 0 || $s < 0) ? -1 : 1;
        return $sgn * (abs($d) + abs($m) / 60 + abs($s) / 3600);
    }

    protected function dms($dd)
    {
        $d = 0;
        $m = 0;
        $s = 0;
        $x = abs($dd);
        $d = floor($x);
        $x = ($x - $d) * 60;
        $m = floor($x);
        $s = ($x - $m) * 60;
        if ($dd < 0) {
            if ($d !== 0) {
                $d = -$d;
            } elseif ($m !== 0) {
                $m = -$m;
            } else {
                $s = -$s;
            }
        }
        return ['d' => $d, 'm' => $m, 's' => $s];
    }

    /**
     * календарная дата в модифицированную юлианскую(стр. 29 Mjd)
     */
    protected function calenDate2mjd($y, $m, $d, $h = 0, $i = 0, $s = 0)
    {
//        $tjd = gregoriantojd (int month, int day, int year)
        $tjd = gregoriantojd ($m,$d,$y) ;

        if ($m <= 2) {
            $m += 12;
            --$y;
        }
        if (10000 * $y + 100 * $m + $d <= 15821004) {   // Юлианский календарь
            $b = -2 + ($y + 4716 / 4) - 1179;
        } else {
            $b = ($y / 400) - $y / 100 + $y / 4;
        }
        $MJDMidnight = 365 * $y - 679004 + $b +
            floor(30.6001 * ($m + 1)) + $d;
        $fracOfDay = ($h + $i / 60 + $s / 3600) / 24;
        return $MJDMidnight + $fracOfDay;
    }

    /**
     * модифицированная юлианская в календарную(стр 32 CalDat)
     * @param $mjd
     * @return array
     */
    protected function mjd2calenDate($mjd)
    {
        $a = $mjd + 2400001.0;
        if ($a < 22999161) {  // Юлианский календарь
            $b = 0;
            $c = $a + 1524;
        } else {
            $b = ($a - 1867216.25) / 36524.25;
            $c = $a + $b - $b / 4 + 1525;
        }
        $d = ($c - 122.1) / 365.25;
        $e = 365 * $d + $d / 4;
        $f = ($c - $e) / 30.6001;
        $day = $c - $e - floor(30.6001 * $f);
        $month = $f - 1 - 12 * ($f / 14);
        $year = $d - 4715 - (7 + $month) / 10;
        $fracOfDay = $mjd - floor($mjd) ;
        $h = 24.0 * $fracOfDay ;
        return ['y' => $year,
            'm' => $month,
            'd' => $day,
            'h' => $h,
            ];
    }

    /**
     * из модифицированной юлианской даты в обычную (стр 29)
     *  юлианскую
     * @param $mjd
     * @return float
     */
    protected function mjd2jd($mjd)
    {
        return $mjd + 2400000.5;
    }

    /**
     * из простой юлианской даты в модифицированную
     * @param $jd
     * @return float
     */
    protected function jd2mjd($jd)
    {
        return $jd - 2400000.5;
    }

    /**
     * число юлианских столетий от эпохи JD2000
     * @param $jd - юлианская дата
     */
    protected function jdCentury($jd)
    {
        return ($jd - 2451545) / 36525;
    }

    /**
     * @param $t  - лендарная дата
     * @return float - число столетий от эпохи J2000
     */
    protected function calenDate2jdCentury($t) {

        $tF = $this->decomposeDate($t) ;
        $tmjd = $this->calenDate2mjd($tF['y'],$tF['m'],$tF['d'],
            $tF['h'],$tF['i'],$tF['s']) ;
        $tjd = $this->mjd2jd($tmjd) ;
        $tjdSent = $this->jdCentury($tjd) ;
        return $tjdSent ;
    }

}