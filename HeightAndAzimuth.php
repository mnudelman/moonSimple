<?php
/**
 * класс Высота и азимут Солнца(может быть и Луны),
 * установки
 * $sha->setTheta($theta)     // положение плоскости Pdl
 * -> setDayTimeType($type)   // светлое или тёмное время
 * вариант задания точки B на окружности Pphi - сечение
 * ->setBPointByAngle($angleGrad)      // через центральный угол
 * ->setBPoint($x,$y)                  // или через локальные кординаты
 */

class HeightAndAzimuth extends Common
{
    protected $anglePhi;       // широта (рад)
    protected $aPoint = [] ; // точка восхода
    protected $bPoint = [] ; // точка заката
    protected $RAvect = [] ; // вектор в точку A
    protected $RBvect = [] ; // вектор в точку B
    protected $sunHeight ;   // высота над горизонтом
    protected $sunAzimuth ;
    protected $cosSunAzimuth ;
    protected $aEastWest = [] ; // направляющий вектор прямой Восток - Запад
    protected $aProject = [] ;  // напр вектор проекции луча на Солнце
    protected $productEWProj = [] ;
    protected $currentDayTimeType ;  // текущий тип времени суток (d - тёмное| l)
    protected $theta ;               // положение плоскости Pdl
    //-----------------------------------------------------------//
    public function __construct()
    {
        parent::__construct() ;
        $this->init() ;

    }
    private function init() {
        $this->aPoint = $this->pointInit() ;
        $this->bPoint = $this->pointInit() ;
        $this->RAvect = $this->coordinateInit() ;
        $this->RBvect = $this->coordinateInit() ;
    }
    public function setLatitude($phiGrad) {
        $this->anglePhi = deg2rad($phiGrad) ;    // / 180 * pi() ;
        return $this ;
    }
    public function setTheta($theta) {
        $this->theta = $theta ;
        return $this ;
    }
    public function setBPointByAngle($angleGrad) {
        $angleRad = deg2rad($angleGrad) ;  // /180 * pi() ;
        $x = cos($angleRad) ;
        $y = sin($angleRad) ;
        $this->setBPoint($x,$y) ;
        return $this ;
    }
    /**
     * задаются координаты в местной для плоскости Pphi системы
     * координат (третья координата z=0)
     * @param $x
     * @param $y
     */
    public function setBPoint($x,$y) {
        $this->RBvect['loc'] = ['x' => $x,'y' => $y] ;
        $this->RBvect['X1'] = $this->transFromPhiLocToX1Y1Z1($x,$y) ;
        $x1 = $this->RBvect['X1']['x'] ;
        $y1 = $this->RBvect['X1']['y'] ;
        $z1 = $this->RBvect['X1']['z'] ;
        $this->RBvect['X'] = $this->transToXYZ($x1,$y1,$z1) ;
        return $this ;
    }

    public function setDayTimeType($dayTimeType) {
        $this->currentDayTimeType = $dayTimeType ;
        return $this ;
    }
    /**
     * получить координаты Солнца в точке B={Bx,By} в локальной системе Pphi
     * @param $Bx
     * @param $By
     */
    public function getSunCoordinate($Bx = false,$By = false) {
//        $this->x = $this->dayTime() ;
        $res = $this->getSunCoordinateDo($Bx,$By) ;
        return $res ;
    }
    /**
     * получить координаты Солнца в точке B={Bx,By} в локальной системе Pphi
     *  вернее в локальной системе наблюдателя, ибо азимут и высота считаются
     * относительно диска наблюдателя Do
     * координата Bx,By может быть определена пр обращении или через setBPoint
     * @param $Bx
     * @param $By
     */
    public function getSunCoordinateDo($Bx = false,$By = false) {
//        $this->x = $this->dayTime() ;
        if (false !== $Bx) {         // координата задана при обращении
            $this->setBPoint($Bx,$By) ;
        }

        $this->aEWClc() ;             // восток-запад
        $this->ApointClc() ;          // точка A на Pdl
        $this->heightClc() ;          // высота над горизонтом
        $this->azimuthClc() ;         // расчёт азимута
//      добавим sin через вект произведение
        $aVP = $this->angleVectorProduct($this->aProject,$this->aEastWest['X']) ;
        $v  = $this->vectorProduct($this->aProject,$this->aEastWest['X']) ;
        $this->productEWProj = $v ;
        $aGrad = round($this->sunAzimuth / pi() * 180,6) ;
        $hGrad = round($this->sunHeight / pi() * 180,6) ;
        $aGradTuning = $this->azimuthTuning($aGrad) ;

        $a = $this->productEWProj ;
        $angleC = $this->getAngleC() ;
        return [
            'h' => $this->sunHeight,
            'a' => $this->sunAzimuth,
            'grad' => [
                'h' => $hGrad,
                'a' => $aGrad,
                'aTuning' => $aGradTuning,     // это правильный азимут
                ],
            'cosA' => $this->cosSunAzimuth,
            'vectSin' => $aVP,  // отладочное
                                // ['sinA' => $sinA, 'angle' => $angle,'angleGrad' => $angleGrad] ;
            'angleC' => $angleC,            // отладочный параметр
        ] ;

    }

    /**
     * это проверка угла == 90 между
     * см. раздел 1.9 Азимут. Проекция на сечение P_{\varphi}. текста
     *  расчёт показал, что этот угол точно 90
     * @return array
     */
    private function getAngleC() {
        $p1 = $this->pDl['p1'] ;
        $p2 = $this->pDl['p2'] ;
        $a = $this->transFromPhiLocToX1Y1Z1($p1['x'],$p1['y']) ;
        $b = $this->transFromPhiLocToX1Y1Z1($p2['x'],$p2['y']) ;
        $X = $this->RAvect['X'] ;
        $X1 = $this->transToX1Y1Z1($X['x'],$X['y'],$X['z']) ;
        $C = ['x' => $X1['x'],'y' => $X1['y'],'z' => 0] ; // точка C на плоскости P_phi
        $BX1 = $this->RBvect['X1'] ;
        $BC = ['x' => $BX1['x'] - $C['x'],
            'y' => $BX1['y'] - $C['y'],
            'z' => $BX1['z'] - $C['z']] ;
        $ab = ['x' => $b['x'] - $a['x'],
            'y' => $b['y'] - $a['y'],
            'z' => $b['z'] - $a['z'],
            ] ;
        $angle = $this->angleBetweenVectors($BC,$ab) ;
        return $angle ;


    }
//    private function signCompare($x,$y) {
//        return ($x > 0 && $y > 0) ||
//            ($x === 0 && $y === 0) ||
//            ($x < 0 && $y < 0) ;
//    }
    private function azimuthTuning($aGrad) {
        $dayTimeTypeLight = ($this->currentDayTimeType === self::DAY_TIME_LIGHT) ;
        $a = $this->productEWProj ;
        $b = $this->RBvect['X'] ;
        $eq = $this->signCompare($a['x'],$b['x']) &&
            $this->signCompare($a['y'],$b['y']) &&
            $this->signCompare($a['z'],$b['z']) ;
        if ($eq) {
            if ($dayTimeTypeLight) {
                $aGrad = 90 +  $aGrad ;
            }else {
                $aGrad = 270 +  $aGrad ;
            }

        } else {
            if ($dayTimeTypeLight) {
                $aGrad = 90 - $aGrad;
            }else {
                $aGrad = 270 -  $aGrad ;
            }
        }
        if ($aGrad < 0) {
            $aGrad = 360 + $aGrad ;
        }
        if ($aGrad >= 360) {
            $aGrad = $aGrad - 360 ;
        }
        return $aGrad ;
    }
    /*
     * Вычислить высоту Солнца
     */
    public function heightClc() {
       $r = $this->angleBetweenVectors($this->RAvect['X'],$this->RBvect['X']) ;
        $this->sunHeight = $r['angle'] ;
    }
    public function azimuthClc() {
        $a = $this->RAvect['X'] ;
        $b = $this->RBvect['X'] ;


        $nBoA = $this->vectorProduct($a,$b) ; // норм вектор для плоскости AoB
        $this->aProject = $this->vectorProduct($nBoA,$b) ;

         $r = $this->angleBetweenVectors($this->aProject,$this->aEastWest['X']) ;
        $this->sunAzimuth = $r['angle'] ;
        $this->cosSunAzimuth = $r['cosA'] ;
    }

    /**
     * Наклон лунного серпа
     * Определение знака. проверяем совпадение 2 векторов.
     * 1. vp - это вект. произведение RAvect - вектор вточку A на плоскости
     *    Pface и oZVect - ось oZ.  RAvect,oZVect лежат в плоскости Pface, то
     *    vp параллелен нормали к Pface. Если совпадает, то угол > 0, Если противоположен,
     *    то угол < 0.  Это справедливо для  Pface, проходящей через ось oZ Земли.
     *    Нас интересует дублёр Pfacemoon. Там знак надо менять на противоположный
     * @return array['angle' => $angle,'cosA' => $cosAangle]
     */
    public function angleCrescentMoon() {
        $this->ApointClc() ;
        $oZVect = ['x' => 0,'y' => 0,'z' => 1] ;
        $r = $this->angleBetweenVectors($this->RAvect['X'],$oZVect) ;
        $vp = $this->vectorProduct($this->RAvect['X'],$oZVect) ;
        $theta =$this->theta ;
        $nTheta = ['x' => -sin($theta),'y' => cos($theta)] ;
        // !! знак наоборот !! //
        $sgn = ($this->signCompare($nTheta['x'],$vp['x']) &&
            $this->signCompare($nTheta['y'],$vp['y'] ) ) ? -1 : 1 ;
        $r['angle'] = $sgn * $r['angle'] ;
         $r['vp'] = ' x=> ' . round($vp['x'],3) . ' y=> ' . round($vp['y'],3)   ;
        $r['nTheta'] = ' nThx=> ' . round($nTheta['x'],3) .
            ' nThy=> ' . round($nTheta['y'],3)   ;
        return $r ;
    }
     protected function aEWClc() {
        $nX1 = ['x' => 0,'y' => 0,'z' => 1] ; // нормальный вектор к Pphi в oX1Y1Z1
        $nX = $this->transToXYZ($nX1['x'],$nX1['y'],$nX1['z']) ;
        $b = $this->RBvect['X'] ;
        $v = $this->vectorProduct($nX,$b) ;
        $this->aEastWest['X'] = $v ;
    }

    /**
     * вычислить точку A на Pdl. перпендикуляр опущен из B на Pdl
     *  Вычисление. n_dl = {-sin(theta), cos(theta), 0} - норм вектор Pdl
     * Он же направляющий вект. для прямой AB. Получанм систему и находим A
     * Всё в системе oXYZ
     */
    protected function ApointClc() {
        $theta = $this->theta ;
        $tanTheta = tan($theta) ;
        $Bx = $this->RBvect['X']['x'] ;
        $By = $this->RBvect['X']['y'] ;
        $Bz = $this->RBvect['X']['z'] ;

        $up =  $By * $tanTheta + $Bx ;
        $dn = $tanTheta ** 2 + 1 ;
        $Ax = $up / $dn ;
        $Ay = $tanTheta * $Ax  ;
        $Az = $Bz ;       // AB перпендикуляр Pdl -> перпенд oZ
        $this->RAvect['X'] = ['x' => $Ax,'y' => $Ay,'z' => $Az] ;
    }
    private function coordinateInit() {
        return [
        'loc'=> ['x' => 0, 'y' => 0], // локальная (плоскость Pphi)
        'X' => ['x' => 0, 'y' => 0,'z' => 0], // система X - "солнечная"
        'X1' => ['x' => 0, 'y' => 0,'z' => 0], // система X1 - "земная"
        ] ;
    }
    private function pointInit() {
        return ['x' => 0,'y' => 0,'z' => 0] ;
    }

}