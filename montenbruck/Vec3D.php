<?php


namespace montenbruck;

use montenbruck\Montenbruck ;

class Vec3D extends Montenbruck
{
    protected $m_Vec = [0,0,0];         // компоненты вектора
    protected $m_phi;                   // полярный угол (азимут)
    protected $m_theta;                 // полярный угол (высота)
    protected $m_r;                     // модуль вектора
    protected $m_polar = [0,0,1] ;
// ------------------------------------------------------------//
    public function __construct() {
        parent::__construct();
    }
    public function setVect($x,$y,$z) {
        $this->m_Vec[0] = $x;
        $this->m_Vec[1] = $y;
        $this->m_Vec[2] = $z ;
        $this->calcPolarAngles() ;
        return $this ;

    }
    public function setPolar($phi,$theta,$r = 1) {
        $this->m_phi = $phi ;
        $this->m_theta = $theta ;
        $this->m_r = $r ;
        $this->m_polar = [$phi,$theta,$r] ;
        $this->calcVect();
        return $this ;
    }
    public function getVect() {
        $x = $this->m_Vec[0] ;
        $y = $this->m_Vec[1] ;
        $z = $this->m_Vec[2] ;
        return ['x' => $x, 'y' => $y, 'z' => $z] ;
    }
    public function getPolar() {
        return [
            'phi' => $this->m_phi,
            'theta' => $this->m_theta,
            'r' => $this->m_r,
        ] ;
    }
    private function calcVect() {
        $rho = $this->m_r * cos($this->m_theta) ;
        $this->m_Vec[0] = $rho * cos($this->m_phi) ;     // x
        $this->m_Vec[1] = $rho * sin($this->m_phi) ;     // y
        $this->m_Vec[2] = $this->m_r * sin($this->m_theta) ;  // z
    }
    private function calcPolarAngles__() {
        $x = $this->m_Vec[0] ;
        $y = $this->m_Vec[1] ;
        $z = $this->m_Vec[2] ;
        // проекция на плоскость x,y
        $rhoSqr = $x * $x + $y * $y ;
        // модуль вектора
        $this->m_r = sqrt($rhoSqr + $z * $z) ;
        if ($x == 0 && $y == 0) {
            $this->m_phi = 0 ;
        } elseif ($x == 0) {
            $this->m_phi = ($y > 0) ? pi() / 2 : -pi() / 2 ;
        } else {
            $this->m_phi = atan($y/$x) ;
        }
        $this->m_phi = ($this->m_phi < 0) ? $this->m_phi + 2*pi() : $this->m_phi ;
        $rho = sqrt($rhoSqr) ;
        if ($rho == 0 && $z == 0) {
            $this->m_theta = 0 ;
        } elseif ($rho == 0) {
            $this->m_theta = ($z > 0) ? pi() / 2 : -pi() / 2 ;
        } else {
            $this->m_ptheta = atan($z/$rho) ;
        }
        $this->m_polar = [$this->m_phi,$this->m_theta,$this->m_r] ;
    }
    private function calcPolarAngles()
    {
        $x = $this->m_Vec[0];
        $y = $this->m_Vec[1];
        $z = $this->m_Vec[2];
        // проекция на плоскость x,y
        $rhoXY = $x * $x + $y * $y;
        // нормируем x,y
        if ($rhoXY == 0 ) {
            $phi = 0 ;
        }else {
            $modXY = sqrt($rhoXY) ;
            $xCos = $x / $modXY ;
            $ySin = $y / $modXY ;
            $phi = $this->phiXYclc($xCos,$ySin) ;
        }

        $r = sqrt($rhoXY + $z * $z) ;
        if ($r == 0) {
            $theta = 0 ;
        }else {
            $theta = asin($z/$r) ;
        }
        $this->phi = $phi ;
        $this->m_theta = $theta ;
        $this->m_r = $r ;
        $this->m_polar = [$phi,$theta,$r] ;
    }

    /**
     * вычисляет угол на плоскости oXY
     * x,y отнормированы (x^2 + y^2 === 1)
     * @param $x
     * @param $y
     * @return float|int
     */
    private function phiXYclc($x,$y) {
        if ($x == 0 && $y == 0) {
            $phi = 0 ;
        }elseif ($y == 0) {
            $phi = ($x > 0) ? 0.0 : pi() ;
        }elseif ($x == 0) {
            $sgn = ($y > 0) ? 1 : -1 ;
            $phi = $sgn * pi()/2 ;
        }elseif ($x > 0 && $y > 0) {   // 1 квадрант
            $phi = acos($x) ;
        }elseif ($x < 0 && $y > 0) {   // 2 квадрант
            $phi = acos($x) ;
        }elseif ($x < 0 && $y < 0) {   // 3 квадрант
            $phi = -acos($x) + 2 * pi() ;
        }elseif ($x > 0 && $y < 0) {   // 4 квадрант
            $phi = asin($y) ;
        }
        return $phi ;

    }
}