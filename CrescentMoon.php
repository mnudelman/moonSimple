<?php
/**
 * класс лунный серп,
 * выполняет расчёт наклона
 * установки
 * $sha->setTheta($theta)     // положение плоскости Pdl
 * -> setDayTimeType($type)   // светлое или тёмное время
 * вариант задания точки B на окружности Pphi - сечение
 * ->setBPointByAngle($angleGrad)      // через центральный угол
 * ->setBPoint($x,$y)                  // или через локальные кординаты
 */

class CrescentMoon extends HeightAndAzimuth
{
    public function angleCrescentMoon() {
        $this->ApointClc() ;
        $oZVect = ['x' => 0,'y' => 0,'z' => 1] ;
        $r = $this->angleBetweenVectors($this->RAvect['X'],$oZVect) ;
        return $r ;
    }

}