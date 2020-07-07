<?php
/**
 * Class MoonControlPoints - вспомогательный класс для задания контрольных точек
 * орбиты Луны. Точки - это новолуние, полнолуние, след. новолуние
 * Атрибутами являются текущая дата и объекты орбитыЗемли и Луны одновременно
 */

class MoonControlPoints extends Common
{
    private $mOrbitObj ;                 // объект - орбита Луны
    private $eOrbitObj ;                 // объект - орбита Земли
    private $testDt ;                    // тестовая дата
    //-------------------------------------------------------//
    public function setMoonOrbit($mOrbit) {
        $this->mOrbitObj = $mOrbit ;
        return $this ;
    }
    public function setEarthOrbit($eOrbit) {
        $this->eOrbitObj = $eOrbit ;
        return $this ;
    }
    public function setDt($dt) {
        $this->testDt = $dt ;
        return $this ;
    }
    public function pointsGo()
    {
        $eOrbitObj = $this->eOrbitObj;
        $mOrbitObj = $this->mOrbitObj;
        $testDt = $this->testDt;
        $rMoonPar = $mOrbitObj->setTestDT($testDt)
            ->getPar();
        $rMoonPer = $rMoonPar['period'];
        $newMoonDate = $rMoonPer['d0'];   // дата новолуния
        $this->moonMonth['dBeg'] = $rMoonPer['dBeg'];    // дата новолуния
        $this->moonMonth['dEnd'] = $rMoonPer['dEnd'];    // дата след новолуния
        $this->moonMonth['T'] = $rMoonPer['T'];    // период (дней)
        $this->moonMonth['dMiddle'] = $rMoonPer['dMiddle'];
        $thetaMoon0 = $eOrbitObj->setTestDT($testDt)
            ->getTheta($newMoonDate);
//// запихиваем контрольные точки
        $ts = strtotime($rMoonPer['dBeg']);
        $theta = $eOrbitObj->getTheta($ts, true) - $thetaMoon0;
        $mOrbitObj->setControlPoint($ts, $theta);
        $ts = strtotime($rMoonPer['dMiddle']);
        $theta = $eOrbitObj->getTheta($ts, true) - $thetaMoon0;
        $mOrbitObj->setControlPoint($ts, $theta + pi());
        $ts = strtotime($rMoonPer['dEnd']);
        $theta = $eOrbitObj->getTheta($ts, true) - $thetaMoon0;
        $mOrbitObj->setControlPoint($ts, $theta + 2 * pi());


        return [
            'dBeg' => ['date' => $this->moonMonth['dBeg'],
                'ts' => strtotime($this->moonMonth['dBeg']),
                'tF' => $this->decomposeDate($this->moonMonth['dBeg'])],
            'dMiddle' => ['date' => $this->moonMonth['dMiddle'],
                'ts' => strtotime($this->moonMonth['dMiddle']),
                'tF' => $this->decomposeDate($this->moonMonth['dMiddle'])],
            'dEnd' => ['date' => $this->moonMonth['dEnd'],
                'ts' => strtotime($this->moonMonth['dEnd']),
                'tF' => $this->decomposeDate($this->moonMonth['dEnd'])],
        ];
    }
}