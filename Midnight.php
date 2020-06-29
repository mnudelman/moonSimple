<?php
/**
 * Class Midnight - полночь
 * выделил для расчётов psi0 и ts0 для лунного цикла
 * Берём первый день цикла. Определяем для него psi0.
 * p10 - восход в 1 день; p20 - закат.
 * Для других дней поступаем след образом:
 * 1. как обычно получаем точки p1 - восхода и p2 - заката
 * 2. по разности положения с p10,p20 вычисляем время p1.ts ; p2.ts
 * 3. point0.ts определяем по разности положения с p1 (или p2)
 * т.е. смысл в том, что point0.ts вычисляется не по дате, а через
 * начало лунного цикла. За счёт этого надеюсь уменьшить погрешность
 * определения времени в последующих днях цикла
 */

class Midnight extends Common
{
    private $newMoonDate;        // текущее новолуние
    private $moonTheta0;         // theta в момент новолуния
    private $date0;              // текщая дата
    private $mdPoint0 = [         // точка - полночь в 1 день цикла
        'psi' => 0,
        'ts' => 0
    ];
    private $mdPoint = [          // точка - полночь
        'psi' => 0,
        'ts' => 0
    ];
    private $lsObj;
    private $firstDate;         // первый день цикла
    private $firstDay = [        //
        'date' => '',
        'ts0' => 0,            // полночь первого дня
        'psi0' => 0,           // центральный угол полночи
        'up' => [             // восход Луны
            'psi' => 0,
            'ts' => 0,
            'theta' => 0
        ],
        'down' => [          // закат Луны
            'psi' => 0,
            'ts' => 0,
            'theta' => 0
        ],
    ];

    //----------------------------------------------------//
    public function setNewMoonDate($newMoonDate)
    {
        $this->newMoonDate = $newMoonDate;
        return $this;
    }

    public function setMoonTheta0($theta)
    {
        $this->moonTheta0 = $theta;
        return $this;
    }

    public function setCurrentDate($dt)
    {
        $this->date0 = $dt;
        return $this;
    }

    public function setLatitudeSection($lsObj)
    {
        $this->lsObj = $lsObj;
        return $this;
    }

    /**
     * первый день нового лунного цикла
     */
    private function firstDay()
    {
        $firstDate = $this->firstDateClc();    // дата первого дня
        $lsObj = $this->lsObj;
        $orbirEarth = new Orbit();
        $orbirEarth->setOrbitType(Common::ORBIT_TYPE_ELLIPT)   //тип орбиты (круговая|эллиптическая
        ->setPlanetId(Common::PLANET_ID_EARTH) //- ид планеты (Земля|Луна)
        ->setTestDT($this->date0);        //- тестовый момент для выбора параметров орбиты

//        $this->moonTheta0 = $orbirEarth->getTheta($this->newMoonDate);
        // надо здесь поставить полночь
        $date0Theta = $orbirEarth->getTheta($firstDate);
        $lsObj->setOZPlane($date0Theta);  // секущая плоскость (например, Pdl
        $r = $lsObj->intersectPointsClc();
        $psiIntervalDark = $r['psiInterval'][self::DAY_TIME_DARK]; // дуга с тёмной стороны
        $psiBegDark = $psiIntervalDark[0];
        $psiEndDark = $psiIntervalDark[1];
        //-----------------------------------------
        $psi0 = ($psiBegDark + $psiEndDark) / 2;    // полночь
    }

    /**
     * дата первых суток цикла
     */
    public function getFirstDate()
    {
        $tfMidnight = $this->decomposeDate($this->newMoonDate);
        $tfMidnight['h'] = 0;
        $tfMidnight['i'] = 0;
        $tfMidnight['s'] = 0;
        $tsFirst = strtotime($tfMidnight['y'] . '-' .
                $tfMidnight['m'] . '-' . $tfMidnight['m']) +
            24 * 3600;
        $tfFirst = $this->decomposeDate($tsFirst, true);
        $firstDate = $tfFirst['y'] . '-' . $tfFirst['m'] . '-' .
            $tfFirst['d'];

        return $firstDate;
    }
}