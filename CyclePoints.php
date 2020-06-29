<?php
/**
 * Class CalculationPoints - точки расчёта
 * точки расчёта расположены по окружности сечения Pphi - по широте  phi
 * возможны варианты: max интервал обхода - 1 сутки(24 часа), т.е. один полный оборот
 * выбор шага: центральный угол(дуга) | время (час:мин)
 * начало/конец интервала: град1,град2 | час1:час2 | восход,закат
 * три вида зависимых координат: угол <-> относительное время <-> абсолютное время
 *  главный ключевой атрибут timestamp. По нему выполняется сортировка
 * точка может иметь атрибуты : восход|закат   полдень|полночь
 * полная структура:
 *  ts => [ 'atr' => ['psi' => 0, 'dtime' => 0,'time' => 0], // связанные координаты точки
 *          'spec' => {'up'|'down' | 'midnight' | 'noon'}, // спец характеристики
 *           'clc' => [atr1,atr2,..]                    // расчитанные параметры
 *        ]
 * расчитанные задаются вектором и могут быть любыми
 * начало:
 * $cp->setCurrentDate($currentDate)           // текущая дата
 * -> setPoint0($psi)                          // точка полночь
 * -> setUpPoint($x,$y,$timeFormat,$azimuth)   // восход
 * -> setDownPoint($x,$y,$timeFormat,$azimuth) // закат
 * -> setPsiInterval($psiInterval)             // угловые интервалы восход-закат и закат-восход
 * -> setCycle($unit,$beg,$end,$nSteps)        // границы цикла в единицах времени или угла
 */

class CyclePoints extends Common
{
    const CYCLE_UNIT_ANGLE = 0 ; // параметры цикла в градусах
    const CYCLE_UNIT_TIME = 1 ;  // параметры цикла - время в формате tf = 'hh:mm:ss' -
                                 // tf с начала суток
    private $currentDate ;       // текущая дата
    private $point0 = [          // начальная точка( полночь -начало суток)
        'ts' => 0,
        'theta' => 0,            //  положение Pdl, при котором получено начальная точка
        'psi'   => 0,            // центр. угол в сечении Pphi для точки
        ] ;
    private $point1 = [
        'psi'   => 0,            // центр. угол в сечении Pphi для точки (x=1,y=0)
        'ts' => 0
    ] ;
    private $upPoint = [] ;               // точка восхода
    private $downPoint = ['ts' => 0,'psi' => 0,'timeFormat'=>['h'=>0,'m'=>0,'s'=>0],
        'azimuth' => 0] ;               // азимут
    private $points = [] ;
    private $cycle = [
        'unit' => '',
        'beg' => ['psiGrad' => 0,'psi' => 0,
            'timeFormat' => ['h'=>0,'m'=>0,'s'=>0], 'ts' => 0],
        'end' => ['psiGrad' => 0,'psi' => 0,
            'timeFormat' => ['h'=>0,'m'=>0,'s'=>0], 'ts' => 0],
        'step' => ['dpsi' => 0,'dts' => 0],
        'nSteps' => 0,

    ] ;
    private $psiInterval = [] ;
    private $cycleIndex = [] ;
    private $indexPoint = 0 ;
    private $thetaTimes =              // время для кооректировки положение Pdl
        ['00:00:00','8:00:00','16:00:00','23:59:59'] ;
    //--------------------------------------------------------//
    public function setCurrentDate($currentDate) {
        $this->currentDate = $currentDate ;
        $this->point0['ts'] = strtotime($currentDate) ;
        return $this ;
    }

    /**
     * @param $thetaTimes - список моментов корректировки положеения Pdl в формате
     *                      [ts1=>theta1,ts2=>theta2,..]
     * спиcок помещается в общий список points c типом theta
     * @return $this
     */
    public function setThetaTimes($thetaTimes) {
        $this->thetaTimes = [] ;
        for ($i = 0; $i < sizeof($thetaTimes); $i++) {
            $tf = $this->decomposeDate($thetaTimes[$i]) ;
            $ts = $this->realTsClc($tf) ;
            $this->thetaTimes[] = [$ts,$thetaTimes[$i]] ;
        }
        return $this ;
    }
    /**
     * полночь
     * @param $psi
     * @return $this
     */
    public function setPoint0($psi,$ts = false) {
//        $this->point0['theta'] =  $theta ;
        $this->point0['psi'] = $psi ;
        if (false !== $ts) {
            $this->point0['ts'] = $ts ;
        }
        return $this ;
    }

    /**
     * восход
     * @param $x
     * @param $y
     * @param $timeFormat
     * @param $azimuth
     * @return $this
     */
    public function setUpPoint($timeFormat,$azimuth,$theta,$psiGrad) {
//        $ts = $this->realTsClc($timeFormat) ;
        $ts = $this->dateFormatToTs($timeFormat) ;      // здесь время установлено точно
        $this->upPoint = [
        'time' => $timeFormat,
        'ts' => $ts,
        'azimuth' => $azimuth,
            'theta' => $theta,
            'psiGrag' => $psiGrad,
            'psi' => deg2rad($psiGrad),
        ] ;
//        $tsDebug = $this->decomposeDate($this->upPoint['ts'],true) ;
        return $this ;
    }

    /**
     * Закат
     * @param $timeFormat
     * @param $azimuth
     * @return $this
     */
    public function setDownPoint($timeFormat,$azimuth,$theta,$psiGrad) {
//        $ts = $this->realTsClc($timeFormat) ;
        $ts = $this->dateFormatToTs($timeFormat) ;      // здесь время установлено точно
        $this->downPoint = [
            'timeFormat' => $timeFormat,
            'ts' => $ts,
            'azimuth' => $azimuth,
            'theta' => $theta,
            'psiGrag' => $psiGrad,
            'psi' => deg2rad($psiGrad),
        ] ;
//        $tsDebug = $this->decomposeDate($this->downPoint['ts'],true) ;
        return $this ;
    }
    public function dumpPoints() {
        var_dump($this->points);
        return $this ;
    }
    /**
     * расчёт ts для спец точек, в которых момент времени задан относительно начала суток
     * в описатель времени начала суток добавляются атрибуты времени точки ['h','m','s']
     * @param $timeFormat = ['h' => ..,'m' => .., 's' => ..]
     * @return $ts - сформированный ts для точки
     */
    private function realTsClc($timeFormat) {
        $t0 =    // начало суток
            $this->decomposeDate($this->point0['ts'],true) ;
        $h = $timeFormat['h'] ;
        $m = $timeFormat['m'] ;
        $s = $timeFormat['s'] ;
        $tsDelta = abs($h) * 3600 + abs($m) * 60 + abs($s) ;
        if ($h < 0 || $m < 0 || $s < 0) {
            $tsDelta = - $tsDelta ;
        }
        return $this->point0['ts'] + $tsDelta ;
    }

    /**
     * угловой интервал для светлой и тёмной частей суток
     * @param $psiInterval
     * @return $this
     */
    public function setPsiInterval($psiInterval) {
        $this->psiInterval = $psiInterval ;
        $lightPsiInterval = $psiInterval[Common::DAY_TIME_LIGHT] ;
//        $psiBeg = $this->normalizeAngle($lightPsiInterval[0]) ;
//        $psiEnd = $this->normalizeAngle($lightPsiInterval[1]) ;
        $psiBeg = $lightPsiInterval[0] ;
        $psiEnd = $lightPsiInterval[1] ;

        $this->upPoint['psi'] = $psiBeg ;
        $this->downPoint['psi'] = $psiEnd ;
        return $this ;
    }

    /**
     *  параметры цикла - подготовка цикла к работе
     *  если unit = 'угол' => beg,end - просто угол в град
     *  если unit = 'время' => beg,end -в формате 'hh:mm:ss'
     * @param $unit
     * @param $beg
     * @param $end
     * @param $nSteps
     * @return $this
     */
    public function setCycle($unit,$beg,$end,$nSteps) {
        $this->cycle['unit'] = $unit ;
        $this->cycle['nSteps'] = $nSteps ;
        if  ($unit === self::CYCLE_UNIT_ANGLE ) {
            $this->cycle['beg']['psiGrad'] = $beg ;
            $this->cycle['end']['psiGrad'] = $end ;

        } else {
            $begF = $this->decomposeDate($beg) ;
            $endF = $this->decomposeDate($end) ;
            $this->cycle['beg']['timeFormat'] =
                ['h' => $begF['h'],'m' => $begF['i'],'s' => $begF['s']] ;
            $this->cycle['end']['timeFormat'] =
                ['h' => $endF['h'],'m' => $endF['i'],'s' => $endF['s']] ;

        }
        $this->preparePoints()         // реквизиты спец точек
        ->cycleGenerate()              // генерировать точки расчёта
        ->addSpecPoints()              // добавить к точкам расчёта спец точки
        ->prepareCycleIndex()          // отсортировать весь список точек по ts
        ->setIndexTop() ;              // установить начало перебора
        return $this ;
    }
    public function setIndexTop() {
        $this->indexPoint = 0 ;
        return $this ;
    }

    /**
     * выбрать очередную точку в последовательности ts - это может быть
     * простая точка (тип = p) или спец (pUp|pDown|p0 - полночь|p1 - начало psi=0)
     * @return bool|mixed
     */
    public function getNext() {
        $i = $this->indexPoint ;
        $r = false ;
        if ($i < sizeof($this->cycleIndex)) {
           $ts =  $this->cycleIndex[$i] ;
           $r = $this->points[$ts] ;
           $r['ts'] = $ts ;
            $this->indexPoint++ ;
        }
        return $r ;
    }

    /**
     * становить значение для текущей точки:
     * используется для сохранения вычисленных значений
     */
    public function setAttribute($name,$value) {
        $ts =  $this->cycleIndex[$this->indexPoint-1] ;
        $this->points[$ts][$name] = $value ;
        return $this ;
    }
    /**
     * настройка всех спец точек -
     * заполнение координатных полей ts,psi
     * вычисления выполняются на основе известных значений для
     * спец точек.
     */
    private function preparePoints() {
//      пересчёт на point1 - начало координат в сечении Pphi.
//       здесь psi = 0 ; надо выч ts
        $angleBegGrad = $this->upPoint['psiGrad'] ;
        $dtSec = round($angleBegGrad * Common::MINUTES_IN_DEGREE * 60,0) ;
        $tsBeg = $this->upPoint['ts'] ;
        $this->point1['ts'] = $tsBeg - $dtSec ;
//      пересчёт на point0 - полночь. Здесь известен ts. надо выч psi
        $dPsiGrad =
            (($this->upPoint['ts'] - $this->point0['ts'])/60) / Common::MINUTES_IN_DEGREE ;

        $dPsi = deg2rad($dPsiGrad) ;      //     / 180 * pi() ;

        $this->point0['psi'] = $this->upPoint['psi'] - $dPsi ;
//     пересчёт параметров цикла
        if ($this->cycle['unit'] === self::CYCLE_UNIT_ANGLE ) {    // интервал цикла - угол
            $this->cycle['beg']['psi'] = deg2rad($this->cycle['beg']['psiGrad']) ; // / 180 * pi() ;
            $this->cycle['end']['psi'] = deg2rad($this->cycle['end']['psiGrad']); // / 180 * pi() ;
            $tsStart = $this->point1['ts'] ;
            $dts = $this->cycle['beg']['psiGrad'] * self::MINUTES_IN_DEGREE * 60 ;
            $this->cycle['beg']['ts'] = $tsStart + $dts ;
            $dts = $this->cycle['end']['psiGrad'] * self::MINUTES_IN_DEGREE * 60 ;
            $this->cycle['end']['ts'] = $tsStart + $dts ;
        }else {                            // интервал цикла - время
            $tsStart = $this->point0['ts'] ;
            $this->cycle['beg']['ts'] =
                $this->realTsClc($this->cycle['beg']['timeFormat']) ;
            $this->cycle['end']['ts'] =
                $this->realTsClc($this->cycle['end']['timeFormat']) ;

            $point0PsiGrad = rad2deg($this->point0['psi']) ;
            $dtsBegMin = ($this->cycle['beg']['ts'] - $tsStart) / 60 ;
            $this->cycle['beg']['psiGrad'] =
                $point0PsiGrad + $dtsBegMin / self::MINUTES_IN_DEGREE ;
//                ($this->cycle['beg']['ts'] - $tsStart) / 60
//                                                 (self::MINUTES_IN_DEGREE * 60) ;
            $dtsEndMin = ($this->cycle['end']['ts'] - $tsStart) / 60 ;
            $this->cycle['end']['psiGrad'] =
                $point0PsiGrad + $dtsEndMin  / self::MINUTES_IN_DEGREE ;
//                ($this->cycle['end']['ts'] - $tsStart) /
//                (self::MINUTES_IN_DEGREE * 60) ;
            $this->cycle['beg']['psi'] = deg2rad($this->cycle['beg']['psiGrad']) ;
            $this->cycle['end']['psi'] = deg2rad($this->cycle['end']['psiGrad']) ;
        }
        // шаг цикла в ед угла и времени - равнозначны
        $this->cycle['step']['dts'] = ($this->cycle['end']['ts'] -
                $this->cycle['beg']['ts']) / $this->cycle['nSteps'] ;
        $this->cycle['step']['dpsi'] = ($this->cycle['end']['psi'] -
                $this->cycle['beg']['psi']) / $this->cycle['nSteps'] ;
        return $this ;
    }

    /**
     * генерация точек расчёта на осноывании параметров цикла
     */
    private function cycleGenerate() {
        $this->points = [] ;
        $psiBeg = $this->cycle['beg']['psi'] ;

        $psiUp = $this->upPoint['psi'] ;
        $psiDn = $this->downPoint['psi'] ;

        $tsUp = $this->upPoint['ts'] ;
        $tsDn = $this->downPoint['ts'] ;

        $tsBeg = $this->cycle['beg']['ts'] ;
        $dpsi = $this->cycle['step']['dpsi'] ;
        $dts = $this->cycle['step']['dts'] ;
        $tsEnd =  $this->cycle['end']['ts'] ;
        $psi = $psiBeg ;
        $ts = $tsBeg ;
        while (($ts += $dts) < $tsEnd) {
            $psi += $dpsi;
//            $lightFlag = $psi > $psiUp && $psi < $psiDn ;    // светлое время
            if ($tsUp < $tsDn) {
                $lightFlag = ($ts >= $tsUp && $ts < $tsDn) ;
            }else {
                $lightFlag = ($ts < $tsDn || $ts >= $tsUp) ;
            }
            $dayTime = ($lightFlag) ? Common::DAY_TIME_LIGHT : Common::DAY_TIME_DARK ;
            $this->points[$ts] = ['psi' => $psi,'type' => 'p',
                'dayTime' => $dayTime] ;
        }
        return $this ;
    }
    private function dayTimeClc($psi) {
        $psiUp = $this->upPoint['psi'] ;
        $psiDn = $this->downPoint['psi'] ;
        $lightFlag = $psi > $psiUp && $psi < $psiDn ;    // светлое время
        $dayTime = ($lightFlag) ? Common::DAY_TIME_LIGHT : Common::DAY_TIME_DARK ;
        return $dayTime ;
    }
    /**
     * добавление в точки расчёта спец точек
     * @return $this
     */
    private function addSpecPoints() {
        $psi = $this->point0['psi'] ;
        $dayTime = $this->dayTimeClc($psi) ;
        $this->points[$this->point0['ts']] =  ['psi' => $psi,'dayTime' => $dayTime,
            'type' => 'p0']  ;

        $psi = $this->point1['psi'] ;
        $dayTime = $this->dayTimeClc($psi) ;
        $this->points[$this->point1['ts']] =  ['psi' => $psi,'dayTime' => $dayTime,
            'type' => 'p1']  ;

        $psi = $this->upPoint['psi'] ;
        $dayTime = $this->dayTimeClc($psi) ;
        $this->points[$this->upPoint['ts']] = ['psi' => $psi,'dayTime' => $dayTime,
            'type' => 'pUp','azimuth' => $this->upPoint['azimuth']]  ;

        $psi = $this->downPoint['psi'] ;
        $dayTime = $this->dayTimeClc($psi) ;
        $this->points[$this->downPoint['ts']] =  ['psi' => $psi,'dayTime' => $dayTime,
            'type' => 'pDown','azimuth' => $this->downPoint['azimuth']]  ;
        for ($i = 0; $i < sizeof($this->thetaTimes); $i++) {
            $ts = $this->thetaTimes[$i][0] ;
            $this->points[$ts] = ['type' => 'pTheta'] ;
        }
        return $this ;
    }

    /**
     * весь массив точек расчёта сортируется по ts
     * @return $this
     */
    private function prepareCycleIndex() {
        $this->cycleIndex = array_keys($this->points) ;
        sort($this->cycleIndex) ;
        return $this ;
    }
}