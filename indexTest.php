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
    <title>sunrise</title>
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
include_once __DIR__ . '/php-moon-phase-master/Solaris/MoonPhase.php';
// create an instance of the class, and use the current time
$ts = strtotime('2020-05-31 22:20:00') ;
$moon = new Solaris\MoonPhase($ts);
$age = round($moon->get('age'), 1);
$stage = $moon->phase() < 0.5 ? 'waxing' : 'waning';
$distance = round($moon->get('distance'), 2);

echo 'next_new_moon: ' . $moon->get_phase('next_new_moon') . '<br>' ;
$next = gmdate('G:i:s, j M Y', $moon->get_phase('next_new_moon'));
echo "The moon is currently $age days old, and is therefore $stage. " . '<br>';
echo "It is $distance km from the centre of the Earth. " . '<br>';
echo "The next new moon is at $next." . '<br>';



include_once __DIR__ . '/Common.php';
include_once __DIR__ . '/montenbruck/Montenbruck.php';
include_once __DIR__ . '/montenbruck/Vec3D.php';
include_once __DIR__ . '/montenbruck/Mat3D.php';
include_once __DIR__ . '/montenbruck/CoordinateSystem.php';

$cs = new CoordinateSystem() ;
//$t = '2020-05-09 23:36:00' ;
//$t = '2020-05-09 20:00:00' ;

//$t = '2020-05-21 20:00:00' ;
//$t = '2020-05-07 15:45:00' ;
//$t = '2020-05-21 05:03:00' ;
//$t = '2020-05-23 04:34:00' ;
$t = '2020-05-31 22:20:00' ;
//$v = [1,2,3] ;
//$vOut = $cs->setTime($t)
//->equ2ecl($v) ;
//var_dump($vOut);

//'OREN' => ['name' => 'Orenburg',
//    'lat' => 51.768199,      // широта,
//    'long' => 55.096955],    // долгота
$town = 'OREN' ;
    $lat  = 51.768199 ;      // широта,
    $long  =55.096955 ;    // долгота
//$town = 'GRINWICH' ;
//$lat  = 51.507351 ;
//$long = 0 ;


echo '<b>'. 'town: ' . $town . '; t: ' . $t . '</b><br>' ;

$vOut = $cs->setTime($t)
//->setGeographCoord(51.4851,-0,0045)
    ->setGeographCoord($lat,$long)
    ->miniMoon() ;
$vOut1 = $cs->miniMoon1() ;
var_dump($vOut);
var_dump($vOut1);
 ?>
</body>
</html>