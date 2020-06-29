<?php
/**
 * угол между касательными к плоскостям PoZ, Pphi и
 * азимут точек восхода, заката
 * PoZ - общее обозначение плоскости(сечения), проходящего через ось
 * oZ - параллельную оси вращения Солнца (например,Pdl
 * или Pface - плоскость, фиксир видимую сторону Луны)
 * Pphi - плоскость(сечение) по широте phi град
 * Time: 21:43
 */
class AnglePoZPphi extends Common
{
    private $theta ;       // угол - положение плоскости Pdl
    private $anglePhi ;         // угол - широта (рад)
    private $pDl ;         // точки восхода, заката ['p1' => ,'p2'=>..]
    private $tangPphi = ['x' => 0,'y' => 0,'z' => 0] ; // касательная к окруж
                                                       // на широте phi
    private $tangPdl =  ['x' => 0,'y' => 0,'z' => 0] ; // касательная к пл Pdl
    private $cosAlpha ;
    private $sinAlpha ;
    //---------------------------------------------//
    public function __construct()
    {
        parent::__construct() ;
        $this->cosAlpha = cos($this->AXIAL_TILT) ;
        $this->sinAlpha = sin($this->AXIAL_TILT) ;

    }

    /**
     * @param $phi - широта (град)
     */
    public function setLatitudeAngle($phi,$radFlag = false)
    {
        $this->anglePhi = (false === $radFlag) ? deg2rad($phi) : $phi
        ;
        return $this;

    }
    public function setPoints($p1,$p2) {
        $this->pDl['p1'] = $p1 ;
        $this->pDl['p2'] = $p2 ;
        return $this ;
    }
    /*
     * Расчёт угла между касательными к сечениям Pphi (широта) и
     *    Pdl в точках p1,p2 - восход/закат
     * @return $tau =
     * ['angle' => [восход => .., закат => ..], - угол между касательными
     * 'azimuth'=> [восход => .., закат => ..]  - азимут точек
     *  ]
     */
    public function angleClc()
    {
//        $theta = $this->theta ; // угол поворота плоскости Pdl в oXYZ
        $p1 = $this->pDl['p1'] ;
        $p2 = $this->pDl['p2'] ;
        if ($p1['type'] === self::POINT_TYPE_SUNDOWN) {
            $pTemp = $p1 ;
            $p1 = $p2 ;
            $p2 = $pTemp ;
        }
        $theta = $p1['theta'] ;
        $tau1 = $this->tauClc($p1,$theta) ;
        $theta = $p2['theta'] ;
        $tau2 = $this->tauClc($p2,$theta + pi() ) ;

        $azimuth =  [
            $p1['type'] => $this->angleToAzimuth($p1['type'],$tau1),
            $p2['type'] => $this->angleToAzimuth($p2['type'],$tau2),
            ] ;
        $tau = [
            'angle' => [$p1['type'] => $tau1, $p2['type'] => $tau2],
            'azimuth' => $azimuth] ;

        return $tau ;
    }

    /**
     * пересчёт угла между касательными в азимут
     * @param $tauType - восход | закат
     * @param $tau     - угол между касательными
     * @return float   - азимут
     */
     private function angleToAzimuth($tauType, $tau) {
        $tauGrad = round($tau/pi() * 180,6) ;
        return  ($tauType === self::POINT_TYPE_SUNRISE) ?
            180 - $tauGrad : 360 - $tauGrad;
     }

    /**
     * @param $p       - точка, в которой ищется касательная
     * @param $beta   -  угол между oX и проекцией R_p на oXY
     *                      (R_p - вектор из центра o  точку p)
     * @return float
     */
    private function tauClc($p, $beta) {
        $t1 = $this->tangPphiClc($p['x'],$p['y']) ;

        // Для Pdl надо координаты, полученные на Pphi умножить * cos(phi)
// z1 = sin(phi). (радиус Земли сокращаем из всех выражений)
        $cosPhi = cos($this->anglePhi) ;
        $sinPhi = sin($this->anglePhi) ;
        $x1 = $p['x'] * $cosPhi ;
        $y1 = $p['y'] * $cosPhi ;
        $z1 = $sinPhi ;
        $t2 = $this->tangPdlClc($x1,$y1,$z1,$beta) ;

        // угол определям через скалярное произведение двух касательных
        $modT1 = sqrt($t1['x'] ** 2 + $t1['y'] ** 2 + $t1['z'] ** 2) ;
        $modT2 = sqrt($t2['x'] ** 2 + $t2['y'] ** 2 + $t2['z'] ** 2) ;
        $scalT1T2 = ($t1['x'] * $t2['x'] + $t1['y'] * $t2['y'] + $t1['z'] * $t2['z']) ;
        $cosTau = $scalT1T2 / ($modT1 * $modT2) ;
        return acos($cosTau) ;

    }
    /*
     * Вычислить касательную к окружности на широте $anglePhi ; // широта (рад)
     *  берётся вектор на плоскости P_phi с координатой z=0, т.к.
     *  речь идёт о направлении => можно считать,что вектор лежит
     *  на координатной плоскости oX1Y1
     */
    private function tangPphiClc($x,$y) {
        $x1 = -$y ;
        $y1 = $x ;
        $z1 = 0 ;
        $this->tangPphi = $this->transToXYZ($x1,$y1,$z1) ;
        return $this->tangPphi ;

    }
    /*
     * на входе точка в системе oX1Y1Z1
     * на выходе касательная в системе oXYZ
     */
    private function tangPdlClc($x1,$y1,$z1,$theta) {
        // перевести точку в oXYZ
        $R = $this->transToXYZ($x1,$y1,$z1) ; // это вектор в точку x1,y1,z1
        $x = $R['x'] ;
        $y = $R['y'] ;
        $z = $R['z'] ;
        // из R получить вектор в плоскости Pdl
        // терперь его надо повернуть, чтобы получить касательную
        // проекцию n_xy = (x,y) кладём на ось oZ
        // проекцию z кладём на плоскость oXY, но в обратную сторону
        $zTemp = sqrt($x ** 2 + $y ** 2) ;

        $x = -$z * cos($theta) ;
        $y = -$z * sin($theta) ;
        $z = $zTemp ;
        $this->tangPdl = ['x' => $x, 'y' => $y, 'z' => $z] ;
        return $this->tangPdl ;
    }
}