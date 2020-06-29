<?php
/**
 * Эллиптическая траектория движения Земли вокруг Солнца
*  иллюстрация sin(a) = a при малом a
*pi/500: 0.0062831853071796; 0.006283143965559
*pi/600: 0.005235987755983; 0.0052359638314196
*pi/700: 0.0044879895051283; 0.0044879744389254
*pi/800: 0.0039269908169872; 0.003926980723806
 * фрагмент таблицы перигелия, офелия орбиты Земли
 * http://www.astropixels.com/ephemeris/perap2001.html
2011     Jan 03  18:32   0.9833413 AU    7681 km      Jul 04  14:54   1.0167404 AU    4501 km     365.77 days
2012     Jan 05  00:32   0.9832841 AU    -871 km      Jul 05  03:32   1.0166751 AU   -5270 km     366.25 days
2013     Jan 02  04:38   0.9832905 AU      82 km      Jul 05  14:44   1.0167085 AU    -268 km     363.17 days
2014     Jan 04  11:59   0.9833347 AU    6701 km      Jul 04  00:13   1.0166816 AU   -4287 km     367.31 days
2015     Jan 04  06:36   0.9832774 AU   -1874 km      Jul 06  19:40   1.0166821 AU   -4214 km     364.78 days
2016     Jan 02  22:49   0.9833039 AU    2097 km      Jul 04  16:24   1.0167509 AU    6080 km     363.68 days
2017     Jan 04  14:18   0.9833094 AU    2919 km      Jul 03  20:11   1.0166756 AU   -5190 km     367.65 days
2018     Jan 03  05:35   0.9832843 AU    -845 km      Jul 06  16:47   1.0166961 AU   -2129 km     363.64 days
2019     Jan 03  05:20   0.9833012 AU    1681 km      Jul 04  22:11   1.0167543 AU    6590 km     364.99 days
2020     Jan 05  07:48   0.9832436 AU   -6935 km      Jul 04  11:35   1.0166943 AU   -2399 km     367.10 days
 * я использую эти значения
 * В результате расстояние Земли от Солнца (от центра до центра)
 * изменяется со средними значениями
 * 0,9832899 AU (147,098,074 км) в перигелии (ближайший)
 * до 1,0167103 AU (152,097,701 км) в афелии (самый отдаленный).
 * здесь же и хорошая таблица равноденствий на 100 лет
 * можно не заморачиваться на другие источники
 * парметры орбиты
 * http://astro-bratsk.ru/yearbook/342-perihelion-earth.html
 * перигелий (Perihelion) - 0.983 а.е.   147117000 км
 * афелий (Aphelion)     - 1.0167 а.е.  152083000 км    -
 * T = 365 дней 6 часов 9 мин 10 сек
 *  Равноденствие по UTC-0
 * https://ru.wikipedia.org/wiki/%D0%A0%D0%B0%D0%B2%D0%BD%D0%BE%D0%B4%D0%B5%D0%BD%D1%81%D1%82%D0%B2%D0%B8%D0%B5
 * 2010 	20 	17:32:13
   2011 	20 	23:21:44
   2012 	20 	05:14:25
   2013 	20 	11:02:55
   2014 	20 	16:57:05
   2015 	20 	22:45:09
   2016 	20 	04:30:11
   2017 	20 	10:28:38
   2018 	20 	16:15:27
   2019 	20 	21:58:25
   2020 	20 	03:50:36
   2021 	20 	09:37:27
   2022 	20 	15:33:23
   2023 	20 	21:24:24
   2024 	20 	03:06:21
   2025 	20 	09:01:25
 */

class Elliptical
{
    const ELLIPSE_AF = 1.0167543; //AU 152.083 ; // *10^6 km ------ 1.0167; //а.е. *10^6 km  - афелий
    const ELLIPSE_PE = 0.9833012 ; //AU 147.117 ; // *10^6 km ------0.983 ; //а.е; // *10^6 km  - перегелий
// интервал 2019 -220 гг 367.10 дней
    private $YEAR_DAYS =367 ; // //365 ; //
    private $ADD_DAY = 0.103 ; //0.256365741 ; // ; // ; // 6/24 + (9 +10/60)/(24*60) ; // довесок до целого года

    private $perihelion = [] ;     // афелий для расчёта
    private $eccentricity;             // экстентриситет
    private $semiaxisA;     // полуось a - большая
    private $semiaxisB;     // полуось b - малая
    private $ellipseArea;   // площадь эллипса
    private $angleElement;  // угловой элемент - первичное разбиение
    private $nElements = 1000; // число первичных разбиений окружности
    private $currentPhi ;      // текущий угол, начиная от главной оси
    private $currentArea ;     // площадь, покрытая $currentPhi
    private $dayNumber ;       //
    private $resTab = [] ;     // таблица результатов
    private $epsilonArea = 10 ** (-10) ;
    private $monthTab = [] ;
    //==========================================================//
    public function __construct()
    {
        $this->init() ;
    }

    private function init()
    {
      $this->semiaxisA = (self::ELLIPSE_AF + self::ELLIPSE_PE)/2 ;
      $c = (self::ELLIPSE_AF - self::ELLIPSE_PE   )/2 ; // расстояние от фокуса до центра
      $this->eccentricity = $c / $this->semiaxisA ;
      $this->semiaxisB = $this->semiaxisA *
          sqrt(1 - $this->eccentricity ** 2) ;
      $this->ellipseArea = pi() * $this->semiaxisA * $this->semiaxisB ;
      $this->currentPhi = 0 ;
      $this->angleElementClc() ;
      $this->cyclParamsClear() ;
      $this->monthTabDef() ;
    }
    private function monthTabDef() {
        $this->monthTab = [
            '01' => 31,
            '02' => 28,
            '03' => 31,
            '04' => 30,
            '05' => 31,
            '06' => 30,
            '07' => 31,
            '08' => 31,
            '09' => 30,
            '10' => 31,
            '11' => 30,
            '12' => 31,
        ] ;
    }
    public function getAttributes() {
        $p = $this->getPerihelion() ;
        return [
            'a' => $this->semiaxisA,
            'b' => $this->semiaxisB,
            'e' => $this->eccentricity,
            'S' => $this->ellipseArea,
            'area' => $this->ellipseArea,
            'epsilon' => $this->epsilonArea,
            'dateBeg' => $p['dateBeg'],
            'dateEnd' => $p['dateEnd'],
            'perihelion' => $p['perihelion'],
            'aphelion' => $p['aphelion'],
        ] ;
    }
    private function oneDayAreaDef() {
        $tTot = $this->YEAR_DAYS + $this->ADD_DAY ;     // полныйгод
        $a = $this->getPerihelion() ;   // афелий
        $t0 = 1 - $a['add'] ;     // дополнение до полных суток
        $t = $tTot - $t0 ;
        $days = floor($t) ;        // целое число дней
        $tAdd = $t - $days ;
        // пределяем площадь
        $s0 = $this->ellipseArea / $tTot * $t0 ;
        $sDays = $this->ellipseArea / $tTot * $days ;
        $sAdd = $this->ellipseArea - ($sDays + $s0) ;
        return [
            'begin' => ['t' => $t0,'s' => $s0],   // дополн до полных суток
            'middle' => ['t' => $days,'s' => $sDays], // целые сутки
            'end' => ['t' => $tAdd,'s' => $sAdd],   // остаток в конце
        ] ;

    }
    private function cyclParamsClear() {
        $this->currentPhi = 0 ;
        $this->dayNumber = -1 ;
    }
    public function setEpsilon($e) {
        $this->epsilonArea = $e ;
        $this->angleElementClc() ;
        $this->cyclParamsClear() ;
    }

    /**
     * Задать текущий афелий
     */
    private function getPerihelion() {
        //2019     Jan 03  05:20   0.9833012 AU    1681 km      Jul 04  22:11   1.0167543 AU    6590 km     364.99 days
        //2020     Jan 05  07:48   0.9832436 AU   -6935 km      Jul 04  11:35   1.0166943 AU   -2399 km     367.10 days

        $this->perihelion = [
            'year' => 2019,
            'mon'  => 1,
            'day'  => 3,
            'add' => (5 +20/60)/24,   // довесок- время прохождения афелия
            'dateBeg' => '2019-01-03 05:20',
            'dateEnd' => '2020-01-05 07:48',
            'perihelion' => '0.9833012 AU',
            'aphelion' => '1.0167543 AU',

        ] ;
        return $this->perihelion ;
    }
    private function angleElementClc() {
        $this->angleElement = 2 * pi() / $this->nElements ;
    }
    /**
     * строка таблицы результатов
    */
     private function newRow($dayN) {
        return
         [
            'dayN' => $dayN,     // пор N дня, начиная от афелия
            'date' => '',    // календарная дата
             'r' => 0,      // радиус
            'darea' => 0,     // элемент площади, соотв дню
             'area' => 0,     // площадь всего
             'dphi' => 0,    // угол за день
             'phi'  => 0,     // угол относительно гл оси
            'nSteps' => 0,   // число первичных элементиков приформировании площади
            'minPhiStep' => 0, // наименьший шаг
        ] ;
    }

    /**
     * текщий радиус из фокуса в точку на эллипсе
     * из правого фокуса => знак в знаменателе "+"
     */
    private function getCurrentR($currentPhi) {
        $up = $this->semiaxisA * (1 - $this->eccentricity **2) ;
        $down = 1 + $this->eccentricity * cos($this->currentPhi) ;
        return $up / $down ;

    }
    public function resTabClc() {
         $this->resTab = [] ;
         $areaArr = $this->oneDayAreaDef() ;
         foreach ($areaArr as $key => $timeArea) {
             $t = $timeArea['t'] ;     // период дней
             $s =  $timeArea['s'] ;    // площадь, приходящая на период
             $this->resTabClcDo($t,$s) ;
             $tBeg = $areaArr['begin']['t'] ;     // не полные сутки в начале
             $tEnd = $areaArr['end']['t'] ;        // не полные сутки в конце
         }
        $this->dateClc((1 - $tBeg)*24,$tEnd) ; // проставить дату и время
        $this->angleThetaClc() ; // угол поворота плоскости Pdl

         return $this->resTab ;
    }

    /**
     * получить угол плоскости Pdl по дате
     * @param $dat
     */
    public function getTheta($dat) {
        $rowAnsw = $this->findeRowByDate($dat) ;
        return $this->resTab[$rowAnsw]['theta'] ;

    }
    /**
     * Вычисление угла поворота плоскости Pdl относительно весеннего равноденствия
     * площадь, приходящаяся на сутки равноденствия делится пропорционально на
     * back и forw части.
     * back для расчёта угла theta плоскости Pdl в строну уменьшения
     *      даты
     * forw - соответственно в сторону увеличения
     * Т.е. при изменении даты в строну уменьшения добавляется уголок ['equ']['back']['dphi']
     *  при изменении даты в строну увеличения - ['equ']['forw']['dphi']
     */
    private function angleThetaClc() {
        $dEqu = $this->getEquinox() ;
        $d = ['y' => $dEqu['y'],'m' => $dEqu['m'],'d' => $dEqu['d']] ;
        $iEqu = $this->findeRowByDate($d) ;   // строка равноденствия
        if (false === $iEqu) {
            return false ;
        }
        $rowEqu = $this->resTab[$iEqu] ;
        $tEqu = $dEqu['T']['h'] + ($dEqu['T']['m'] + $dEqu['T']['s']/60)/60 ; // в часах
//      надо вычислить уголок, приходящийся на $tEqu
        $currentPhi = $rowEqu['phi'] ;
        $dPhi = $rowEqu['dphi'] ;
        $darea = $rowEqu['darea'] ;
        $dareaBack = $darea/24 * $tEqu ;       // нижняя часть - время от начала суток
        $r = $this->getCurrentR($currentPhi) ;
        $res = $this->oneDayAreaClc($r,$dareaBack) ;
        $backAarea = $res['ds'] ;
        $backPphi =   $res['dphi'] ;
        $forwArea = $darea - $backAarea ;
        $forwPhi = $dPhi - $backPphi ;
        $this->resTab[$iEqu]['equ'] = [
            'back' =>['t' => $tEqu,'darea'=>$backAarea,'dphi' => $backPphi],
            'forw' => ['t' => 24 - $tEqu,'darea'=>$forwArea,'dphi' => $forwPhi]] ;
        $this->resTab[$iEqu]['theta'] = -$backPphi ; // 0 ;
        $theta = $this->resTab[$iEqu]['equ']['forw']['dphi'] ;
        for ($i = $iEqu +1; $i < sizeof($this->resTab); $i++) {
            if (!empty($this->resTab[$i]['dphi'])) {
                $theta += $this->resTab[$i]['dphi'] ;
                $this->resTab[$i]['theta'] = $theta ;
            }
        }
        $theta = -$this->resTab[$iEqu]['equ']['back']['dphi'] ;
        for ($i = $iEqu - 1; $i > 0; $i--) {
            $theta -= $this->resTab[$i]['dphi'] ;
            $this->resTab[$i]['theta'] = $theta  ;
        }
    }
    private function findeRowByDate($d)
    {
        $iFind = false;
        for ($i = 0; $i < sizeof($this->resTab);$i++) {
            $dCur = $this->resTab[$i]['date'] ;
            if ($dCur['y'] == $d['y'] && $dCur['m'] == $d['m']
                && $dCur['d'] == $d['d'] ) {
                $iFind = $i ;
                break ;
            }
        }
        return $iFind ;
    }

    /**
     * весеннее равноденствие
     * @param int $year
     * @return array
     */
    private function getEquinox($year = 2019) {
//  20.03 T 21:58:25
        return ['y' => 2019, 'm' => 3, 'd' => 20,
            'T' => ['h' => 21, 'm' => 58,'s' => 25]] ;
    }
    /**
     * форматировать время в форму d h m
     * @param $t
     */
    private function formatHour($t) {
        $d = 0;
        $h = 0;
        $m = 0;
        if ($t > 24) {
            $d = floor($t/24) ;
            $t -= $d * 24 ;
        }
        $h = floor($t) ;
        $m = round(($t - $h) * 60,4) ;
        return ['d' => $d, 'h' => $h, 'm' => $m] ;

    }
    private function dateClc($tBeg,$tEnd) {
        $currentYear = 2019 ;
        $m = 1 ;
        $d0 = 3 ;
        $h = 5 + 0.3333 ; //   20/60 ;
        $hf = $this->formatHour($tBeg) ;
        $h = '5' . ':' . $hf['m'] ;
        $date0 = ['y' => $currentYear, 'm' => $m, 'd' => $d0, 'h' => $h] ;
        $this->resTab[0]['date'] = $date0 ;
        $dTotYear = 0 ;
        $h = '00:00' ;
        for ($i = 1; $i < sizeof($this->resTab); $i++) {

            $a = $this->getMonth($this->resTab[$i]['dayN'] + $d0 - $dTotYear) ;
            if ($a['m'] === 0) {
                $dTotYear = $a['dTot'] ;
                $currentYear += 1 ;
                $i-- ;
                continue ;
            }
            if ($i === sizeof($this->resTab) - 1) {
                $hf =  $this->formatHour($tEnd * 24) ;
                $h = $hf['h'] .':' .$hf['m'] ;
            }
            $this->resTab[$i]['date'] =
                ['y' => $currentYear,'m' => $a['m'], 'd'=> $a['d'],'h'=> $h];
        }
    }
    private function getMonth($n) {
        $dTot = 0 ;
        $m = 0 ;
        $d = 0 ;
        foreach ($this->monthTab as $mNumber => $mDays) {
            if ($n > $dTot && $n <= $dTot + $mDays) {
                $m = $mNumber  ;
                $d = $n - $dTot ;
                break ;
            }
            $dTot += $mDays ;
        }
        return ['m' => $m,'d' => $d,'dTot' => $dTot] ;
    }
    /**
     * разбросать площадь по дням
     * @param $t
     * @param $s
     */
    private function resTabClcDo($t,$s) {
        $darea = ($t > 1) ? $s/$t : $s ;
        $n = ($t > 1) ? $t : 1 ;
        $n0 = sizeof($this->resTab) ;
        for ($i = 0; $i < $n; $i++) {
            $row = $this->newRow($i + $n0) ;
//       текщий радиус из фокуса в точку на эллипсе
            $r = $this->getCurrentR($this->currentPhi) ;
            $res = $this->oneDayAreaClc($r,$darea) ;
            $row['darea'] = $res['ds'] ;

            $row['dphi'] =   $res['dphi'] ;
            $row['phi'] = $this->currentPhi ;
            $row['nSteps'] = $res['nSteps'] ;
            $row['minPhiStep'] = $res['minPhiStep'] ;
            $row['r'] = $r ;

            $this->currentPhi += $res['dphi'] ;
            $this->currentArea += $res['ds'] ;

            $row['area'] = $this->currentArea ;
            $this->resTab[] = $row ;
        }

    }
    private function oneDayAreaClc($r,$oneDayArea) {
         $ds = 0 ;
         $dphi = 0 ;
         $phiElement = $this->angleElement ;
         $nSteps = 0 ;
         $r2 = $r ** 2 / 2;
         while (abs($ds - $oneDayArea) > $this->epsilonArea) {
//             $simpleS = $r2 * $phiElement / 2; // элементарное приращение площади
             $simpleS = $r2 * $phiElement; // элементарное приращение площади
             $res = $this->simpleDs($ds,$dphi,$simpleS,$phiElement,$oneDayArea) ;
             $ds = $res['ds'] ;
             $dphi = $res['dphi'] ;
             $nSteps += $res['n'] ;
             if (abs($ds - $oneDayArea) > $this->epsilonArea) {
                 $phiElement = $phiElement / 3 ; //5 ;
             }
         }
         return ['ds' => $ds,'dphi' => $dphi,'nSteps' => $nSteps,
             'minPhiStep' => $phiElement] ;
    }
    private function simpleDs($ds,$dphi,$simpleS,$phiElement,$oneDayArea)
    {
        $n = 0 ;
        while ($ds < $oneDayArea) {

            if ($ds + $simpleS < $oneDayArea) {
                $n++ ;
                $ds += $simpleS;
                $dphi += $phiElement;
            }else {
                break ;
            }
        }
        return ['ds' => $ds,'dphi' => $dphi,'n' => $n] ;
    }
}