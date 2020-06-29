<?php
/**
 * Эллиптическая орбита
 * Date: 30.11.19
 * Time: 8:41
 */

class EllipticalOrbit extends Common
{   private $primaryTabs = [] ;  // список всех доступных эллипсов
    protected $perAttr = [] ;    // текущие параметры периода
    protected $ellipseAttr = [] ; // --""--- эллипса
    private $currentTab = [] ;    // текущая таблица точек эллипса
    private $epsilonArea = 10 ** (-4) ;
    private $nElements = 1000; // число первичных разбиений окружности
    private $angleElement;  // угловой элемент - первичное разбиение
    //===========================================//
    public function __construct()
    {
        $this->angleElement = 2*pi()/$this->nElements ;
    }

    public function setting($oPer,$ePar = null) {
        $this->orbitPerClc($oPer);
        if (!is_null($ePar)) {         // только для эллипса
            $rApo = $ePar['rApo'] ;
            $rPeri = $ePar['rPeri'] ;
            $this->ellipseParClc($rApo,$rPeri) ;
//      здесь расчитываем текущую таблицу
            $this->tabClc() ;
        }
        return $this ;
    }

    /**
     * нужна только для круговой орбиты
     * @param bool $ts
     * @param bool $theta
     * @return $this
     */
    public function setControlPoint($ts = false,$theta = false) {
        return $this ;
    }
    /**
     * перенос из исходных атрибутов периода во внутренние
     * @param $oPer
     */
    protected function orbitPerClc($oPer)
    {
        $this->perAttr['tsBeg'] = strtotime($oPer['dBeg']);
        $this->perAttr['tsEnd'] = strtotime($oPer['dEnd']);
        $this->perAttr['ts0'] = strtotime($oPer['d0']);
        $this->perAttr['T'] = $oPer['T'];
        $n = $this->nElements;
        if ($n > round($this->perAttr['T'] * 5)) {
            $this->nElements = $this->perAttr['T'] * 5;
            $this->angleElement = 2 * pi() / $this->nElements;
        }
        if ($this->nElements < 100) {
            $this->nElements = 100 ;
            $this->angleElement = 2 * pi() / $this->nElements;

        }
    }
    private function ellipseParClc($rApo,$rPeri) {

        $a = ($rApo + $rPeri)/2 ;    // большая полуось
        $c = ($rApo - $rPeri)/2 ;    // смещение фокуса от центра
        $e = $c / $a ;               // эксцентриситет
        $b = $a * sqrt(1 - $e**2) ; // малая полуось
// отнормируем a,b
        $a1 = 1 ;
        $b1 = $b / $a ;
        $s1  =  pi() * $a1 * $b1 ;        // площадь
        $s = pi() * $a * $b ;        // площадь
        $this->ellipseAttr = [
            'a' => $a1,'b' => $b1,'e' => $e,'S' => $s1,
            'source' => [
                'a' => $a,'b' => $b,'e' => $e,'S' => $s,
            ]
        ] ;
    }

    /**
     * вычислить угол по отношению к началу отсчёта(например,равнодентвие)
     * @param $dT
     * @param $timestampFlag - true => время в формате timestamp
     * @return bool|mixed
     */
    public function getTheta($dT,$timestampFlag = false,$noDeltaFlag = false) {
        $ts = ($timestampFlag) ? $dT : strtotime($dT) ;

        if (!$this->getCurrentTab($ts)) {
            return false ;
        }
        $i1 = $this->findRowByTs($ts) ;
        $theta = $this->currentTab[$i1]['theta'] ;
        $ts1 =  $this->currentTab[$i1]['ts'] ;
        if ($ts > $ts1) {
            $dts = $ts  - $ts1 ;
            $r = $this->clcDtheta($dts,$theta) ;
            $theta += $r['dTheta'] ;
        }
        $ts0 = $this->perAttr['ts0'] ;
        $i0 = $this->findRowByTs($ts0) ;
        $theta0 = $this->currentTab[$i0]['theta'] ;
        $r = ($noDeltaFlag) ? $theta : $theta - $theta0 ;
        return $r ;
    }


    /**
     * вычислить отрезок времени по углу
     * @param $dTheta - угол по отношению к равноденствию
     * $dTheta между $theta1,$theta2
     * Соответственно ищем $ts  между $ts1,$ts2
     *
     * @return bool|mixed
     */
    public function getTs($theta) {
//        if (!$this->getCurrentTab($ts)) {
//            return false ;
//        }
        $i1 = $this->findRowByTheta($theta) ;
        $theta1 = $this->currentTab[$i1]['theta'] ;
        $theta2 = $this->currentTab[$i1+1]['theta'] ;
        $ts1 = $this->currentTab[$i1]['ts'] ;
        $ts2 = $this->currentTab[$i1+1]['ts'] ;
        $tsStep = 300 ; //(int) ($ts2 - $ts1) / 1000 ;      //
        $thetaCurr = $theta1 ;
        $tsCurr = $ts1 ;
        $tsCurrPrev = $tsCurr ;
        while ($thetaCurr < $theta) {
            $tsCurrPrev = $tsCurr ;
            $tsCurr += $tsStep ;
            $thetaCurr = $this->getTheta($tsCurr,true,true) ;
        }
        return ( $tsCurrPrev + $tsCurr) / 2 ;
    }

    private function findRowByTs($ts) {
        $i1 = false ;
        for ($i = 0; $i < sizeof($this->currentTab) -1; $i++) {
            $ts1 = $this->currentTab[$i]['ts'] ;
            $ts2 = $this->currentTab[$i + 1]['ts'] ;
            if ($ts >= $ts1 && $ts < $ts2) {
                $i1 = $i ;
                break ;
            }
        }
        return $i1 ;
    }

    /**
     * искать строку по углу theta
     * @param $theta
     * @return bool|int
     */
    private function findRowByTheta($theta) {
        $i1 = false ;
        for ($i = 0; $i < sizeof($this->currentTab) -1; $i++) {
            $theta1 = $this->currentTab[$i]['theta'] ;
            $theta2 = $this->currentTab[$i + 1]['theta'] ;
            if ($theta >= $theta1 && $theta < $theta2) {
                $i1 = $i ;
                break ;
            }
        }
        return $i1 ;
    }


    /**
     * расчёт контрольных точек для тек эллипса
     * @return bool
     */
    private function tabClc() {
        $this->currentTab = [] ;
        $this->fillTabByTimestamp() ;    // проставить ts - атрибут
        $this->fillTabByTheta() ;        // проставить угол
        
        return true ;
    }

    /**
     * протавить timestamp по периоду
     */
    private function fillTabByTimestamp() {
        $ts = $this->firstRow() ;    // первые полные сутки
        $dTs = 24*3600 ;    // сутки
        $tsBeg = $this->perAttr['tsBeg'] ;
        $tsEnd = $this->perAttr['tsEnd'] ;
        $ts0 = $this->perAttr['ts0'] ;      // начало отсчёта (равноденствие)
        if ($ts0 === $tsBeg) {
            $this->currentTab[0]['ts0Flag'] = true ;
        }
        while (($ts += $dTs)  <= $tsEnd) {
            if ($ts > $ts0 && $ts0 > $ts - $dTs) { // вставить начало отсчёта
                $rw = $this->newTabRow() ;
                $rw['ts'] = $ts0 ;
                $rw['ts0Flag'] = true ;
                $this->currentTab[] = $rw ;
            }
            $rw = $this->newTabRow() ;
            $rw['ts'] = $ts ;
            $this->currentTab[] = $rw ;
        }
        if ($tsEnd - ($ts - $dTs) > 0 ) {
            $rw = $this->newTabRow() ;
            $rw['ts'] = $tsEnd ;
            $this->currentTab[] = $rw ;
        }

    }

    /**
     * проставить угол
     */
    private function fillTabByTheta() {
        $this->currentTab[0]['theta'] = 0 ;
        $this->currentTab[0]['area'] = 0 ;
        for ($i = 0; $i < sizeof($this->currentTab) - 1; $i++) {
            $dTs = $this->currentTab[$i+1]['ts'] -    // площадь сектора
                $this->currentTab[$i]['ts']  ;
            $theta = $this->currentTab[$i]['theta'] ;
            $area =  $this->currentTab[$i]['area'] ;

            $r = $this->clcdTheta($dTs,$theta) ;  // расчитать угол сектора

            $this->currentTab[$i]['dTheta'] = $r['dTheta'] ;
            $this->currentTab[$i]['ro'] = $r['ro'] ;
            $this->currentTab[$i]['dArea'] = $r['dArea'] ; // элемент площади, соотв дню
            $this->currentTab[$i]['nSteps'] = $r['nSteps'] ;     // число первичных элементиков приформировании площади
            $this->currentTab[$i]['minThetaStep'] = $r['minThetaStep'] ; // наименьший шаг

            $this->currentTab[$i+1]['theta'] = $theta + $r['dTheta'] ;
            $this->currentTab[$i+1]['area'] = $area + $r['dArea'] ;
        }
    }
    /**
     * начало таблицы - целые сутки
     */
    private function firstRow() {
        $tsBeg = $this->perAttr['tsBeg'] ;
        $dt = $this->decomposeDate($tsBeg,true) ;
        $tsAdd = $dt['h'] * 3600 + $dt['i'] * 60 + $dt['s'] ; // секунд
        $ts0 = $tsBeg - $tsAdd ;

        $ts1 = $ts0 ;     // первый полный день
        $rw = $this->newTabRow() ;
        $rw['ts'] = $tsBeg ;
        $this->currentTab[] = $rw ;
        if ($tsAdd > 0) {
            $rw = $this->newTabRow() ;
//            $this->perAttr['tsBeg'] ;
            $ts1 = $ts0 + 24*3600 ;
            $rw['ts'] = $ts1 ;
            $this->currentTab[] = $rw ;
        }
        return $ts1 ;
    }

    private function clcDtheta($dts,$theta) {
        $ro = $this->getCurrentRo($theta) ;
        $T = $this->perAttr['T']; // период (суток)
        $dts = $dts/(24*3600) ;    // из секунд в сутки
        $s = $this->ellipseAttr['S'] ;     // площадь эллипса
        $ds0 = $s/$T * $dts ;     // площадь сектора <-> интервалу времени $dtsDays
        $ds = 0 ;
        $theta0 = $theta ;
        $dTheta = $this->angleElement ;   // первоначальное приближение
        $n = 0 ;     // число шагов
        while (abs($ds - $ds0) > $this->epsilonArea) {
            $r = $this->fillDs($ds0,$ds,$theta,$dTheta) ;
            $ds = $r['ds'] ;
            $theta = $r['theta'] ;
            $dTheta = $dTheta / 3 ;
            $n += $r['n'] ;
        }
        return ['dArea' => $ds,'dTheta' => $theta - $theta0,
            'nSteps' => $n,'minThetaStep' => $dTheta,'ro'=> $ro] ;

    }

//

    private function clcDts($theta, $theta0, $ts1) {
        $ro = $this->getCurrentRo($theta0) ;
        $T = $this->perAttr['T']; // период (суток)
        $dts = $ts1/(24*3600) ;    // из секунд в сутки
        $s = $this->ellipseAttr['S'] ;     // площадь эллипса
        $ds0 = $s/$T * $dts ;     // площадь сектора <-> интервалу времени $dtsDays
        $ds = 0 ;
//        $stepTheta = 2 * pi() / ($T * 300) ;
        $dTheta = $this->angleElement ;   // первоначальное приближение
        $n = 0 ;     // число шагов
        while (abs($ds - $ds0) > $this->epsilonArea) {
            $r = $this->fillDs($ds0,$ds,$theta,$dTheta) ;
            $ds = $r['ds'] ;
            $theta = $r['theta'] ;
            $dTheta = $dTheta / 3 ;
            $n += $r['n'] ;
        }
        return ['dArea' => $ds,'dTheta' => $theta - $theta0,
            'nSteps' => $n,'minThetaStep' => $dTheta,'ro'=> $ro] ;

    }




    /**
     * замостить сектор ds треугольными элементами
     */
     private function fillDs($ds0,$ds,$theta,$dTheta) {
         $n = 0 ;
         $ds1 = 0 ;
         while ($ds + $ds1 < $ds0) {
             $ro = $this->getCurrentRo($theta) ;
             $ds1 = ($ro ** 2 * $dTheta) / 2 ; // площадь треугольника
             if ($ds + $ds1 < $ds0) {
                 $ds += $ds1 ;
                 $theta += $dTheta ;
                 $n++ ;
             }
         }
        return ['ds' => $ds,'theta' => $theta,'n' => $n] ;
    }
    private function newTabRow() {
        return
            [
                'ts' => 0,     // timestamp
                'ro' => 0,      // радиус
                'dArea' => 0,      // элемент площади, соотв дню
                'area' => 0,       // площадь всего
                'dTheta' => 0,     // угол за день
                'theta'  => 0,     // угол относительно гл оси
                'nSteps' => 0,     // число первичных элементиков приформировании площади
                'minThetaStep' => 0, // наименьший шаг
            ] ;
    }
    /**
     * Из спика выбрать текущую таблицу, подходящую по периоду
     * она становится текущей
     * @param $ts
     */
    private function getCurrentTab($ts) {
        $res = false ;
        $tsBeg = $this->perAttr['tsBeg'] ;
        $tsEnd = $this->perAttr['tsEnd'] ;
        if ($ts >= $tsBeg && $ts <= $tsEnd) {
            $res = true ;
        }else {
            for ($i = 0; $i < sizeof($this->primaryTabs); $i++) {
                $perAttr = $this->primaryTabs[$i]['perAttr'] ;
                $tsBeg = $perAttr['tsBeg'] ;
                $tsEnd = $perAttr['tsEnd'] ;
                if ($ts >= $tsBeg && $ts <= $tsEnd) {
                    $this->perAttr = $perAttr ;
                    $this->ellipseAttr =
                        $this->primaryTabs[$i]['ellipseAttr'] ;
                    $this->currentTab = $this->primaryTabs[$i]['tab'] ;
                    $res = true ;
                    break ;
                }

            }

        }
        return $res ;

    }
     /**
     * текщий радиус из фокуса в точку на эллипсе
     * из правого фокуса => знак в знаменателе "+"
     */
    private function getCurrentRo($theta) {
        $a = $this->ellipseAttr['a'] ;    // большая полуось
        $e = $this->ellipseAttr['e'] ;    // эксцентриситет
//        $up = $this->semiaxisA * (1 - $this->eccentricity **2) ;
//        $down = 1 + $this->eccentricity * cos($this->currentPhi) ;
        $up = $a * (1 - $e **2) ;
        $down = 1 + $e * cos($theta) ;

        return $up / $down ;

    }


}