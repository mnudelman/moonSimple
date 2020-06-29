<?php
/**
 * Class LatitudeSection - сечение по широте
 * Все расчёты, связанные с сечением, в том числе
 * положение наблюдателя и привязка к всемирному времени
 * сечение будем обозначать Pphi,
 * окружность - граница сечения - ringP_phi
 * Pdl - плоскость, проходящая через ось oZ, отделяющая освещённую
 * и тёмную части поверхности
 * theta - угол поворота Pdl вокруг оси oZ
 * ldl - линия пересечения плоскостей P_phi,Pdl
 * p1,p2 - точки пересечения ldl и ringP_phi - эти точки являются
 *         точками восхода/заката
 * O1 - точка пересечения P_phi и оси oZ1 - вращения Земли
 * O2 - точка пересечения P_phi и оси oZ, параллельной оси вращения Солнца
 * локальная ось ox проходит через  O1,O2
 * Обащение. установка:
 * ls->setLatitudeAngle($phi) -  широта
 * ->setOZPlane($theta)       - угол поворота плоскости, проходящей через ось oZ(напр, Pdl)
 */

class LatitudeSection extends Common
{
    protected $theta;          // угол поворота плоскости Pdl (связан с days)
    protected $anglePhi;       // широта (рад)
    protected $x0;             // расстояние O1,O2
    protected $pDl = ['p1' =>[],'p2'=>[]] ;  // точки пересечения l_dl с окружностью l_phi
    protected $psiDark = ['psi1' => 0,'psi2' => 0] ; // угловые компоненты для тёмного времени
    protected $psiInterval =               // границы углового изменения
        ['d' => ['psi1' => 0,'psi2' => 0],  // для тёмного времени
            'l' => ['psi1' => 0,'psi2' => 0],  // светлового времени
        ] ;
    protected $discriminant ;        // дискриминант квадратного уравнения
    protected $currentPolarState ;   // состояние полярныйДень/полярнаяНочь
    private $pdlPhiObj = null ;
    //-------------------------------------------------------//
    /**
     * @param $angle - широта
     */
    public function setLatitudeAngle($phi)
    {
        $this->anglePhi = $phi / 180 * pi() ;
        $this->x0 = tan($this->AXIAL_TILT) * tan($this->anglePhi); //   0.5502 ;
        return $this;

    }

    /**
     * угол плоскости, проходящей через ось oZ,
     * например, Pdl - плоскость отделяющая освещённую
     * и тёмную части поверхности
     */
    public function setOZPlane($theta)
    {
        $this->theta = $theta;
        return $this;
    }

    /**
     * точки пересечения окружности и линии пересечения плоскостей
     * Pphi, Pdl
     */
    public function getIntersectionPoints()
    {
        return $this->pDl ;
    }

    /**
     * можно задать точки p1,p2 и тогда будет пересчитан интервал
     * это требуется,например, после корректировки положения точек p1.p2
     * если выполнть последовательность
     * lsObject->setIntersectionPoints($p1,$p2)->getPsiInterval(),
     * получим новый интервал по факт положению точек
     * @param $p1
     * @param $p2
     * @return $this
     */
    public function setIntersectionPoints($p1,$p2)
    {
        $this->pDl['p1'] = $p1 ;
        $this->pDl['p2'] = $p2 ;
        $this->psiIntervalClc() ;      //  пересчитать интервалы светлой и тёмной области
        return $this ;
    }

    /**
     * интервал - это пара, задающая угловые координаты
     * светлой и тёмной части суток
     * @return array
     */
    public function getPsiInterval()
    {
            return $this->psiInterval ;
    }

    /**
     * углы персечения касательных в точках p1,p2
     * углы нужны для определения азимута в точках восхода,заката
     * @return $tau = ['angle' => [],'azimuth' => []] - угол
     *  мужду касательными и азимут в точках восхода/заката
     */
    public function getIntersectionAngles()
    {
        if (is_null($this->pdlPhiObj)) {
            $this->pdlPhiObj = new AnglePoZPphi() ;
        }
        $ppObj = $this->pdlPhiObj ;
        $tau = $ppObj->angleClc() ;
        return $tau ;
    }
    /**
     *  обнуление результирующих векторов
     */
    private function clcVectIni() {
        $this->pDl = ['p1' =>[],'p2'=>[]] ;  // точки пересечения l_dl с окружностью l_phi
        $this->psiDark = ['psi1' => 0,'psi2' => 0] ; // угловые компоненты для тёмного времени
        $this->psiInterval =               // границы углового изменения
            ['d' => ['psi1' => 0,'psi2' => 0],  // для тёмного времени
                'l' => ['psi1' => 0,'psi2' => 0],  // светлового времени
            ] ;

    }
    /**
     * расчёт точек пересечения плоскостей Pphi,Pdl
     * угол, определяющий положение  плоскости можно задать
     * в параметре
     */
    public function intersectPointsClc($theta = false) {
        if (false !== $theta) {
            $this->setOZPlane($theta) ;
        }
        $this->clcVectIni() ;

        $alpha = $this->AXIAL_TILT;     // наклон оси Земли
        $cosAlpha = cos($alpha) ;
        $theta = $this->theta ;      // угол поворота пересекающей плоскости
        $tgTheta = tan($theta);
        $tgTheta1 = $cosAlpha * $tgTheta;   // угол линии пересечения в Pphi - локальной сист юкоординат
        $r = $this->solvingQuadratic($tgTheta1) ;   // решить квадратное уравнение
        // тип точек: восход/заход
        $p1 = $r['p1'] ;
        $p2 = $r['p2'] ;
        $D = $r['D'];
        $this->discriminant = $D ;



        if ($D > 0) {
            $this->pDl = $this->pointType($p1,$p2,$theta) ;
        }else {
            $this->polarTypeClc($theta) ;
            $this->pDl = $this->polarPointType($p1,$p2) ;
        }

//        if ($this->x0 < 1) {
//            $this->psiDarkClc() ;
//        }elseif ($this->x0 > 1 && $D > 0) {
//            $this->psiDarkClc1() ;
//        } elseif ($this->x0 >= 1 && $D <= 0) {
//            $this->psiDarkClcPolar() ;
//        }
        $this->psiIntervalClc() ;      // расчёт интервала по точкам $pDl = ['p1'=>[],'p2'=>[]]

        $iL = $this->psiInterval['l'] ;    // день
        $tL = abs(($iL[1] - $iL[0]))/(2*pi()) * 24 ;
        $tD = 24 - $tL ;
        return [
            'D' => $D,
            'p1' => $this->pDl['p1'] , //$p['p1'],      //['x'=> $x1,'y' => $y1],
            'p2' => $this->pDl['p2'], //$p['p2'],      //['x'=> $x2,'y' => $y2],
            'x0' => $this->x0 ,
            'theta' => $theta,
            'theta1' => atan($tgTheta1),
            'tanTheta' => $tgTheta,
            'tanTheta1' => $tgTheta1,
            'psi' => $r['psi'],
            'darkLightTime' => ['d' => $tD,'l' => $tL], // продолжительность дня/ночи
            'psiDark' => $this->psiDark,     // угол
            'psiInterval' => $this->psiInterval,
        ] ;
    }

    /**
     * определить интервал изменения центрального угла для
     * тёмной и светлой части
     * на выходе $this->psiInterval
     */
    private function psiIntervalClc() {
        $D = $this->discriminant ;
        if ($this->x0 < 1) {
            $this->psiDarkClc() ;
        }elseif ($this->x0 > 1 && $D > 0) {
            $this->psiDarkClc1() ;
        } elseif ($this->x0 >= 1 && $D <= 0) {
            $this->psiDarkClcPolar() ;
        }

    }
    /**
     * решение квадратого уравнения
     * @param $tgTheta1 - коэффициент прямой - пересечения плоскостей
     * в Pphi - локальной системе
     */
    private function solvingQuadratic($tgTheta1) {
        $x0 = $this->x0 ; //  координата точки  o2 - пересечение oZ и плоскости Pphi
        $tgTheta1_2 = $tgTheta1 ** 2;
        $x1 = 0; $y1 = 0 ;
        $x2 = 0; $y2 = 0 ;
        $psi = 0 ;
        $D = 1 + $tgTheta1_2 * (1 - $x0 ** 2);
        if ($D >= 0) {
            $D = sqrt($D);
            $x1 = ($tgTheta1_2 * $x0 + $D) / (1 + $tgTheta1_2);
            $x2 = ($tgTheta1_2 * $x0 - $D) / (1 + $tgTheta1_2);
            $y1 = $tgTheta1 * ($x1 - $x0);
            $y2 = $tgTheta1 * ($x2 - $x0);
            if ($x0 < 1) {
                $cosPsi = $x1 * $x2 - abs($y1) * abs($y2);
            } else {   // cos,sin меняются местами
                $cosPsi = abs($y1) * abs($y2) - abs($x1) * abs($x2);
            }
            $psi = acos($cosPsi);
//            $t1 = $psi / (2 * pi()) * 24 ; // + 19/60;
//            $t2 = 24 - $t1;
        }
        return [
            'p1' => ['x' => $x1, 'y' => $y1],
            'p2' => ['x' => $x2, 'y' => $y2],
            'D' => $D,
            'psi' => $psi,    // угол для хорды между точками p1,p2
        ] ;
    }
    /**
     * Вычислить угловые компоненты для полярногоДня/Ночи
     */
    private function psiDarkClcPolar() {
        $pd = ['psi1' => 0,'psi2' => 0] ;
        $interval = [
            'd' => [0,0],      // интервал изменения угла для тёмного времени
            'l' => [0,0]] ;    // -------
        if ($this->currentPolarState === self::POLAR_STATE_LIGHT) {
            $pd = ['psi1' => 0,'psi2' => 0] ;
            $interval = [
                'd' => [0,0],      // интервал изменения угла для тёмного времени
                'l' => [0,2 * pi()]] ;    // -------
        }elseif ($this->currentPolarState === self::POLAR_STATE_DARK) {
            $pd = ['psi1' => pi(),'psi2' => pi()] ;
            $interval = [
                'd' => [0,2 * pi()],      // интервал изменения угла для тёмного времени
                'l' => [0,0]] ;    // -------

        }

        $this->psiDark = $pd;
        $this->psiInterval = $interval;

    }
    /**
     * Вычислить угловые компоненты для тёмного времени
     * для случая x0 > 1
     * @param $D - дескриминант (два случая: $D > 0 ; $D < 0
     */
    private function psiDarkClc1() {
        $x1 = $this->pDl['p1']['x'] ;
        $y1 = $this->pDl['p1']['y'] ;
        $x2 = $this->pDl['p2']['x'] ;
        $y2 = $this->pDl['p2']['y'] ;
        $pd = ['psi1' => 0,'psi2' => 0] ;
        $interval = [
            'd' => [0,0],      // интервал изменения угла для тёмного времени
            'l' => [0,0]] ;    // -----------''------------- светлого времени
        if ($x1 == 1 && abs($y1) == 0 && $x2 == -1 && abs($y2 == 0)) {        // 1
            $pd = ['psi1' => pi(), 'psi2' => 0];
            $interval = [
                'd' => [-pi(), 0],      // интервал изменения угла для тёмного времени
                'l' => [0, pi()]];    // -----------''------------- светлого времени
        }elseif($x1 > 0 && $y1 < 0 && $x2 < 0 && $y2 < 0 &&
            $y2 < $y1) {   // 2
            $pd = ['psi1' =>  pi() / 2  + asin($y1),
                'psi2' => pi() / 2  - acos($x2)] ;
            $interval = [
                'd' => [1.5 * pi() - acos($x2),1.5 * pi() + asin($y1)],
                'l' => [- pi() / 2 + asin($y1),1.5 * pi() -  acos($x2)]] ;
        }elseif ($x1 > 0 && $y1 < 0 && $x2 > 0 && $y2 < 0 &&
            $y2 < $y1)    {     // 3
            $pd = ['psi1' =>  -asin($y1) ,
                'psi2' => asin($y2)] ;
            $interval = [
                'd' => [asin($y2), asin($y1)],
                'l' => [asin($y1),2 * pi() - (-asin($y2))]] ;

        }elseif ($x1 > 0 && $y1 > 0 && $x2 > 0 && $y2 > 0 &&
            $y2 < $y1 ) {     // 4
            $pd = ['psi1' =>  asin($y1) ,
                'psi2' => -asin($y2)] ;
            $interval = [
                'd' => [asin($y2), asin($y1)],
                'l' => [asin($y1),2 * pi() + asin($y2)]] ;

        }elseif ($x1 < 0 && $y1 > 0 && $x2 > 0 && $y2 > 0 &&
            $y2 < $y1) {     // 5
            $pd = ['psi1' => pi() / 2 - asin($y1) ,
                'psi2' => pi() / 2 - asin($y2)] ;
            $interval = [
                'd' => [asin($y2), pi() - asin($y1)],
                'l' => [pi() - asin($y1),2 * pi() + asin($y2)]] ;

        }elseif ($x1 == -1 && $y1 == 0 && $x2 == 1 && $y2 == 0) {     // 6
            $pd = ['psi1' => pi() / 2  ,
                'psi2' => pi() / 2 ] ;
            $interval = [
                'd' => [0, pi()],
                'l' => [pi(),2 * pi()]] ;

        }elseif ($x1 < 0 && $y1 < 0 && $x2 > 0 && $y2 < 0 &&
            $y1 < $y2) {     // 7
            $pd = ['psi1' => pi() / 2 + asin($y1) ,
                'psi2' => pi() / 2 + asin($y2)] ;
//            $interval = [
//                'd' => [- pi() - asin($y1),- 2 * pi() + asin($y2)],
//                'l' => [asin($y2), - pi() - asin($y1)]] ;
            $interval = [
                'd' =>[asin($y2),  pi() - asin($y1)],
                'l' =>[pi() - asin($y1), 2 * pi() + asin($y2)], ] ;

        }elseif ($x1 > 0 && $y1 < 0 && $x2 > 0 && $y2 < 0 &&
            $y1 < $y2) {     // 8
            $pd = ['psi1' => - asin($y1) ,
                'psi2' => asin($y2)] ;
            $interval = [
                'd' => [asin($y2), pi() - asin($y1)],
                'l' => [asin($y1), asin($y2)]] ;

        }elseif ($x1 > 0 && $y1 > 0 && $x2 > 0 && $y2 > 0 &&
            $y1 < $y2) {     // 9
            $pd = ['psi1' => - asin($y1) ,
                'psi2' => asin($y2)] ;
//            $interval = [
//                'd' => [asin($y1),-2*pi() + asin($y2)],
//                'l' => [asin($y2),asin($y1)]] ;
            $interval = [
                'd' => [asin($y2),2*pi() + asin($y1)],
                'l' => [asin($y1),asin($y2)]] ;

        }elseif ($x1 > 0 && $y1 > 0 && $x2 < 0 && $y2 > 0 &&
            $y1 < $y2) {     // 10
            $pd = ['psi1' => pi() / 2 - asin($y1) ,
                'psi2' =>  pi() / 2 - asin($y2)] ;
//            $interval = [
//                'd' => [-pi() + asin($y2), asin($y1)],
//                'l' => [-pi() + asin($y2), asin($y1)]] ;

            $interval = [
                'd' => [-pi() - asin($y2), asin($y1)],
                'l' => [ asin($y1), pi() - asin($y2) ] ] ;

        }
        $this->psiDark = $pd;
        $this->psiInterval = $interval;

    }
    /*
     * Вычислить угловые компоненты для тёмного времени
     * на входе вектор pDl = ['p1' => [],'p2 => []],
     *  где p1 - восход; p2 - закат
     */
    private function psiDarkClc() {
        $x1 = $this->pDl['p1']['x'] ;
        $y1 = $this->pDl['p1']['y'] ;
        $x2 = $this->pDl['p2']['x'] ;
        $y2 = $this->pDl['p2']['y'] ;
        $pd = ['psi1' => 0,'psi2' => 0] ;
        $interval = [
            'd' => [0,0],      // интервал изменения угла для тёмного времени
            'l' => [0,0]] ;    // -----------''------------- светлого времени
        if ($x1 == 1 && $y1 == 0 && $x2 == -1 && $y2 == 0) {        // 1
            $pd = ['psi1' => pi(),'psi2' => 0] ;
            $interval = [
                'd' => [-pi(),0],      // интервал изменения угла для тёмного времени
                'l' => [0,pi()]] ;    // -----------''------------- светлого времени

        } elseif ($x1 > 0 && $y1 > 0 && $x2 < 0 && $y2 < 0) {   // 2
            $pd = ['psi1' => asin($y1),
                'psi2' => -acos($x2)] ;
            $interval = [
                'd' => [$pd['psi2'],$pd['psi1']],
                'l' => [$pd['psi1'],2 * pi() + $pd['psi2']]] ;

        } elseif ($x1 > 0 && $y1 > 0 && $x2 > 0 && $y2 < 0) {   // 3
            $pd = ['psi1' => asin($y1),
                'psi2' => asin($y2)] ;
            $interval = [
                'd' => [$pd['psi2'],$pd['psi1']],
                'l' => [$pd['psi1'],2 * pi() + $pd['psi2']]] ;

        } elseif ($x1 < 0 && $y1 > 0 && $x2 > 0 && $y2 < 0) {   // 4
            $pd = ['psi1' => acos($x1),
                'psi2' => asin($y2)] ;
            $interval = [
                'd' => [$pd['psi2'],$pd['psi1']],
                'l' => [$pd['psi1'],2 * pi() + $pd['psi2']]] ;

        } elseif ( $x1 == -1 && $y1 == 0 && $x2 == 1 && $y2 == 0) {   // 5
            $pd = ['psi1' => 0,
                'psi2' => pi()] ;
            $interval = [
                'd' => [0, pi()],
                'l' => [pi(),2 * pi()]] ;

        } elseif ($x1 < 0 && $y1 < 0 && $x2 > 0 && $y2 > 0) {   // 6
            $pd = ['psi1' => -acos($x1),
                'psi2' => asin($y2)] ;
            $interval = [
                'd' => [$pd['psi2'],2 * pi() + $pd['psi1']],
                'l' => [$pd['psi1'],$pd['psi2']]] ;

        } elseif ($x1 > 0 && $y1 < 0 && $x2 > 0 && $y2 > 0) {   // 7
            $pd = ['psi1' => asin($y1),
                'psi2' => asin($y2)] ;
            $interval = [
                'd' => [$pd['psi2'],2 * pi() + $pd['psi1']],
                'l' => [$pd['psi1'],$pd['psi2']]] ;

        } elseif ($x1 > 0 && $y1 < 0 && $x2 < 0 && $y2 > 0) {   // 8
            $pd = ['psi1' => asin($y1),
                'psi2' => acos($x2)] ;
            $interval = [
                'd' => [$pd['psi2'],2 * pi() + $pd['psi1']],
                'l' => [$pd['psi1'],$pd['psi2']]] ;


        } else {
            $pd = ['psi1' => 0,
                'psi2' => 0] ;
            $interval = [
                'd' => [0,0],      // интервал изменения угла для тёмного времени
                'l' => [0,0]] ;    // -----------''------------- светлого времени

        }
        $this->psiDark = $pd ;
        $this->psiInterval = $interval ;
    }

    /**
     * определить тип точек p1, p2 для случая полярной ночи/дня (D < 0)
     * в качестве вектора для определения направления на Солнце используется
     *  отрезок x0 = {0,1}
     *
     * @param $p1
     * @param $p2
     * @param $theta
     */
    protected function polarTypeClc($theta) {
        if ($theta < 0) {
            $this->currentPolarState = ($theta < -pi()) ? self::POLAR_STATE_LIGHT :
                self::POLAR_STATE_DARK ;
        } else {
            $this->currentPolarState = ($theta < pi()) ? self::POLAR_STATE_LIGHT :
                self::POLAR_STATE_DARK ;
        }
    }

    /**
     * для полярногоДня/ночи
     * @param $p1
     * @param $p2
     * @return array
     */
    protected function polarPointType($p1, $p2) {
        $p1['type'] = self::POINT_TYPE_SUNRISE ;
        $p2['type'] = self::POINT_TYPE_SUNDOWN ;
        return ['p1' => $p1, 'p2' => $p2] ;
    }

    /*
     * определить тип точек p1, p2
     * определяем нормальный вектор к прямой p1,p2 , направленный
     * в сторону Солнца. Начальное положение вектора:
     * n0 = {0,1} - Pdl поворачивается на угол pheta. при повороте
     * n = {-sin(pheta), cos(pheta}
     * Далее делаем векторное прозведение (p2 - p1) x n
     * если координата z > 0 => p2 - точка восхода; p1 - закат
     * иначе наооборот
     */
    protected function pointType($p1, $p2, $theta) {
        $z = ($p2['x'] - $p1['x']) * cos($theta) +
            ($p2['y'] - $p1['y']) * sin($theta) ;
        if ($z > 0) {
            $p2['type'] = self::POINT_TYPE_SUNRISE ;
            $p1['type'] = self::POINT_TYPE_SUNDOWN ;
        }else {
            $p1['type'] = self::POINT_TYPE_SUNRISE ;
            $p2['type'] = self::POINT_TYPE_SUNDOWN ;
        }

        // переставить местами, если это не так: p1 - восход ; p2 - закат
        if ($p2['type'] === self::POINT_TYPE_SUNRISE) {
            $pTemp = $p2 ;
            $p2 = $p1 ;
            $p1 = $pTemp ;
        }
        return ['p1' => $p1, 'p2' => $p2] ;
    }

}