<?php
/**
 * Class UpDnMontenbruck -
 * класс для расчёта восхода/заката по алгоритмам из книги
 * Монтенбрук... + Astronomical almanac for the year 1997
 */

class UpDnMontenbruck extends Common
{
    private $latitudeRad ;        // широта рад
    private $longitudeRad ;       // долгота рад
    private $ts ;             // timestamp
    private $upDnObj ;
    //---------------------------------------------//
    public function __construct()
    {
        $this->upDnObj = new CoordinateSystem() ;
    }

    public function setPoint($lat,$long,$radFlag = false) {
        $this->latitudeRad = ($radFlag) ? $lat : deg2rad($lat);
        $this->longitudeRad = ($radFlag) ? $long : deg2rad($long);
        $lat = $this->latitudeRad ;
        $long = $this->longitudeRad ;
        $upDnObj = $this->upDnObj
            ->setGeographCoord($lat, $long,true) ;

        return $this;
    }
    public function setTime($dt,$tsFlag = false) {
        $this->ts = ($tsFlag) ? $dt : strtotime($dt) ;
        return $this ;
    }

    /**
     *  вычислить время на начало тек суток
     */
    private function tsDayBegClc($ts) {
        $df = $this->decomposeDate($ts,true) ;
        $dt = $df['y'] . '-' . $df['m'] . '-' . $df['d']  ;
        return strtotime($dt) ;
    }

    /**
     * расчёт азимута и высоты на тек момент времени
     * @param $ts
     * @param bool $moonFlag - это Луна(true или умолчание) или Солнце (false)
     */
    public function azHClc($ts,$moonFlag = true) {
        $upDnObj = $this->upDnObj ;
        $tF = $this->decomposeDate($ts,true) ;
        $td = $tF['y'] . '-' . $tF['m'] . '-' . $tF['d'] . ' ' .
            $tF['h'] . ':' . $tF['i'] . ':' . $tF['s'] ;
//        $vOut = $upDnObj->setTime($td)
//            ->miniMoon1();
        $upDnObj->setTime($td) ;
        $vOut = ($moonFlag) ? $upDnObj->miniMoon1() : $upDnObj->miniSun1() ;

        $tf = $this->decomposeDate($ts,true) ;
        $dt = $tf['y'] . '-' . $tf['m'] . '-' . $tf['d'] . ' ' .
            $tf['h'] . ':' . $tf['i'] . ':' . $tf['s'] ;

        return [
            'ts' => $ts,
            'dt' => $dt,
            'az' => $vOut['hor']['az'],
            'h' => $vOut['hor']['h'],
        ];

    }
    /**
     * Вычислить точки восхода/заката за
     * текущие сутки
     */
    public function upDownClc()
    {
        $tsBeg = $this->tsDayBegClc($this->ts) ;  // начало суток
        $tsEnd = $tsBeg + 24*3600 ;
        $tsStep = 3600 ;
        $tab = $this->tabClc($tsBeg,$tsEnd,$tsStep) ;
        $upDnTab = [] ;
        $eps = 60 ;  // sec
        for ($i = 0; $i < sizeof($tab) - 1; $i++) {
            $hi = $tab[$i]['h'];
            $hi1 = $tab[$i + 1]['h'];
            if (!$this->signCompare($hi, $hi1)) {
                $type = ($hi > 0) ? 'dn' : 'up';
                $upDnTab[] = [
                    'type' => $type,
                    'beg' => [
                        'ts' => $tab[$i]['ts'],
                        'h' => $hi,
                        'az' => $tab[$i]['az'],
                    ],
                    'end' => [
                        'ts' => $tab[$i + 1]['ts'],
                        'h' => $hi1,
                        'az' => $tab[$i + 1]['az'],
                    ]

                ];
            }
        }
        $res = [] ;
        for ($i = 0; $i < sizeof($upDnTab); $i++) {
            $upDnItem = $this->upDownClcDo($upDnTab[$i],$eps) ;
            $type = $upDnTab[$i]['type'] ;
            $ts = ($upDnItem['beg']['ts'] + $upDnItem['end']['ts']) / 2 ;
            $az = ($upDnItem['beg']['az'] + $upDnItem['end']['az']) / 2 ;
            $h = ($upDnItem['beg']['h'] + $upDnItem['end']['h']) / 2 ;
            $tf = $this->decomposeDate($ts,true) ;
            $dt = $tf['y'] . '-' . $tf['m'] . '-' . $tf['d'] . ' ' .
                $tf['h'] . ':' . $tf['i'] . ':' . $tf['s'] ;
            $res[] = [
                'type' => $type,
                'ts' => $ts,
                'dt' => $dt,
                'az' => $az,
                'h' => $h,
            ] ;
        }
        return $res ;

    }
    private function upDownClcDo($upDnItem,$eps)
    {
        $itemBeg = $upDnItem['beg'];
        $itemEnd = $upDnItem['end'];
        $tsBeg = $itemBeg['ts'];
        $tsEnd = $itemEnd['ts'];
        if ($tsEnd - $tsBeg <= $eps) {
            return $upDnItem;
        } else {
            $tsStep = ($tsEnd - $tsBeg) / 3;
            $tab = $this->tabClc($tsBeg, $tsEnd, $tsStep);
            for ($i = 0; $i < sizeof($tab) - 1; $i++) {
                $hi = $tab[$i]['h'];
                $hi1 = $tab[$i + 1]['h'];
                if (!$this->signCompare($hi, $hi1)) {
                    $type = ($hi > 0) ? 'dn' : 'up';
                    $upDnItem = [
                        'type' => $type,
                        'beg' => [
                            'ts' => $tab[$i]['ts'],
                            'h' => $hi,
                            'az' => $tab[$i]['az'],
                        ],
                        'end' => [
                            'ts' => $tab[$i + 1]['ts'],
                            'h' => $hi1,
                            'az' => $tab[$i + 1]['az'],
                        ]

                    ];
                    $upDnItem = $this->upDownClcDo($upDnItem, $eps);
                    break;
                }

            }
        }
        return $upDnItem ;
    }
    private function tabClc($tsBeg,$tsEnd,$tsStep) {
        $tab = [] ;
        $n = ($tsEnd - $tsBeg) / $tsStep ;
        $ts = 0 ;
//        $lat = $this->latitudeRad ;
//        $long = $this->longitudeRad ;
//        $upDnObj = $this->upDnObj ;
//
//        $vOut = $upDnObj->setGeographCoord($lat, $long,true) ;

        for ($i = 0; $i <= $n; $i++) {
            $ts = $tsBeg + $i * $tsStep;
            $tab[] = $this->azHClc($ts) ;


        }
        return $tab ;
    }

}