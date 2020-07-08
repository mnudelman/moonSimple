<?php
/**
 * User: mickhael
 * Date: 11.06.19
 * Time: 21:03
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>moonsimple</title>
    <?php
    ?>
</head>
<style>
    /* внешние границы таблицы серого цвета толщиной 1px */
    table {border: 1px solid grey;}
    /* границы ячеек первого ряда таблицы */
    th {border: 1px solid grey;}
    /* границы ячеек тела таблицы */
    td {border: 1px solid #44ff7a;}
</style>
<body>
<?php
include_once __DIR__ . '/Common.php';
include_once __DIR__ . '/MoonPar.php';
include_once __DIR__ . '/EarthPar.php';
include_once __DIR__ . '/EllipticalOrbit.php';
include_once __DIR__ . '/CircularOrbit.php';
include_once __DIR__ . '/Orbit.php';

include_once __DIR__ . '/LatitudeSection.php';
include_once __DIR__ . '/Report.php';
include_once __DIR__ . '/DayLengthReport.php';
include_once __DIR__ . '/UpDownTuning.php';
include_once __DIR__ . '/AnglePoZPphi.php' ;
include_once __DIR__ . '/AzimuthReport.php' ;
include_once __DIR__ . "/MoonPhaseReport.php";
include_once __DIR__ . "/MoonAzimuthReport.php";
include_once __DIR__ . "/CyclePoints.php";
include_once __DIR__ . "/HeightAndAzimuth.php";
include_once __DIR__ . "/MoonUpDnReport.php";
include_once __DIR__ . "/MoonPhaseSimple.php";
include_once __DIR__ . "/MoonControlPoints.php";
include_once __DIR__ . '/montenbruck/Montenbruck.php';
include_once __DIR__ . '/montenbruck/Vec3D.php';
include_once __DIR__ . '/montenbruck/Mat3D.php';
include_once __DIR__ . '/montenbruck/CoordinateSystem.php';
include_once __DIR__ . "/MoonUpDnMbruckReport.php";
include_once __DIR__ . "/UpDnMontenbruck.php";
include_once __DIR__ . '/php-moon-phase-master/Solaris/MoonPhase.php';
include_once __DIR__ . '/php-moon-phase-master/Solaris/MoonPhase1.php';

date_default_timezone_set('Etc/GMT+0') ;
//phpinfo() ;
//(new DayLengthReport())
//->reportDo('OREN') ;
//$dl->reportDo('GMT') ;
//$dl->reportDo('JER') ;
//$dl->reportDo('PETER') ;
//$dl->reportDo('ARKH') ;
//$dl->reportDo('MURM') ;


//(new AzimuthReport())
//->reportDo('OREN') ;
//->reportDo('MURM') ;

//(new MoonPhaseReport())
//->reportDo('OREN') ;


//(new MoonAzimuthReport())
//->reportDo('OREN') ;
//->reportDo('GMT') ;

//(new AzimuthReport())
//->reportDo('OREN') ;
//    ->reportDo('GMT') ;

(new MoonUpDnReport())
//    ->reportDo('OREN') ;
//->reportDo('MSC') ;
->reportDo('JER') ;
//->reportDo('EBURG') ;
//->reportDo('PETER') ;
//->reportDo('ARKH') ;
//->reportDo('MURM') ;
//(new AzimuthReport())
//->reportDo('OREN') ;
//    ->reportDo('GMT') ;

//(new MoonUpDnMbruckReport())
//    ->reportDo('OREN') ;
//    ->reportDo('ARKH') ;
//->reportDo('MURM') ;
?>
</body>
</html>