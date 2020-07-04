<?php


class  CircularOrbit extends EllipticalOrbit
{
    private $cntPoints = [] ;      // контрольные точки(граничные условия)
    public function __construct()  {
 }
    /**
    $this->perAttr
    ['tsBeg' => ...,       -- начало периода
     'tsEnd' => ...,       -- конец периода
     'ts0' => ... ,        -- момент <--> theta = 0
     'T' => ....] ;        -- период (суток)

     * вычислить угол по отношению к началу отсчёта(например,равнодентвие)
     * @param $dT
     * @return bool|mixed
     */
    public function getTheta($dT,$timestampFlag = false,$noDeltaFlag = false) {
        $ts = ($timestampFlag) ? $dT : strtotime($dT) ;
//        $ts = strtotime($dT) ;

        $ts0 = $this->perAttr['ts0'] ;
        $deltaTs = $ts - $ts0 ;      // это секунд
        $T = $this->perAttr['T'] ;   //  период орбиты суток
        $Tsec = $T * (24*3600) ;
        $theta = 2*pi()/$Tsec * $deltaTs ;
        return $theta  ;
    }
    public function getTs($theta) {
        return false ;
    }
    public function getTheta1($dT,$timestampFlag = false,$noDeltaFlag = false) {
        $ts = ($timestampFlag) ? $dT : strtotime($dT) ;

        $theta = $this->thetaClc($ts) ;
        return $theta ;
    }
    public function setControlPoint($ts,$theta) {
        $this->cntPoints[] = ['ts' => $ts,'theta' => $theta] ;
        return $this ;
    }
    private function getThetaInterval($ts) {
        $tI = [] ;
        for ($i = 0 ; $i < sizeof($this->cntPoints) - 1; $i++) {
            $tsBeg = $this->cntPoints[$i]['ts'] ;
            $tsEnd = $this->cntPoints[$i + 1]['ts'] ;
            if ($ts >= $tsBeg && $ts <= $tsEnd) {
                $tI = [
                    'beg' => $this->cntPoints[$i] ,
                    'end' => $this->cntPoints[$i + 1] ,
                ] ;
                break ;
            }
        }
        return $tI ;
    }

    /**
     * кусочно-линейная функция
     * @param $ts
     * @return float|int
     */
    private function thetaClc($ts) {
        $tI = $this->getThetaInterval($ts) ;
        $dTheta = ($tI['end']['theta'] - $tI['beg']['theta']) ;
        $dTs =  ($tI['end']['ts'] - $tI['beg']['ts']) ;
        $theta0  = $tI['beg']['theta'] ;
//        $kts = ($ts - $tI['beg']['ts']) / ($tI['end']['ts'] - $tI['beg']['ts']) ; ;
//        $kts = ($ts - $tI['beg']['ts']) / $dTs ; ;
        $kts = $dTheta / $dTs ; ;
        $theta = $theta0 + $kts * ($ts - $tI['beg']['ts']);

        return $theta ;
    }
}