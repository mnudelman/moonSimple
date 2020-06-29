<?php
/**
 * Class UTCsetting - это отдельный класс
 */

class UTCsetting extends LatitudeSection
{
    private $theta0 ;  //  начальноеположение Poz - предполагается на 0:00 часов
    private $timeEpsilon ; // точность определния времени восхода/заката
    private $psi0 ;   // положение точки, соотв $theta0 (половина дуги,
                      // опирающейся на хорду - начальное положение)
    private $dtUp0 ;  // начальное приближение времени точки восхода
    private $dtDown0 ;  // начальное приближение времени точки заката
    public function setTheta0($theta0) {
        $this->theta0 = $theta0 ;
        return $this ;
    }

    /**
     * вычислить начальное положение точки <-> theta0
     */
    private function psi0Clc() {
        $this->setOZPlane($this->theta) ;
        $this->intersectPointsClc() ;  // точки пересечения с ringPphi
        $psiInterval = $this->psiInterval[self::DAY_TIME_DARK] ;
        $psiBeg = $psiInterval[0];
        $psiEnd = $psiInterval[1];
        $this->psi0 = ($psiBeg + $psiEnd) / 2 ;
        $psi0Grad = ($psiBeg - $this->psi0) / pi() * 180 ;
        $this->dtUp0 = self::MINUTES_IN_DEGREE * $psi0Grad ; // минут до восхода
        $psi1Grad = ($psiEnd - $this->psi0) / pi() * 180 ;
        $this->dtDown0 = self::MINUTES_IN_DEGREE * $psi1Grad ; // минут до аката
    }

    /**
     * игра в догонялки
     */
    private function playingCatchUp() {

    }
}