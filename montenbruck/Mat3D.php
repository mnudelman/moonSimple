<?php


namespace montenbruck;
/**
 * Class Mat3D -
 * операции над матрицами
 * @package montenbruck
 */
use montenbruck\Vec3D ;
class Mat3D extends Vec3D
{
    private $matrix = [];      // список матриц ['A' => ['size' => [m,n],
    //                        'tab' =>
    private $POLAR_ADD_ID = '_polar';  // добавка  id вектора
    private $vectors = [];    // векторы ['a' => ['size'=> n, 'vect' => []
    private $matrA = [];
    private $matrB = [];
    private $matrC = [];
    private $vectA = [];
    private $vectB = [];
    private $vectC = [];

    //------------------------------------------------//
    public function __construct()
    {
        parent::__construct();
    }

    public function newMatr($id, $nRows, $mCols, $des = false)
    {
        $tab = [];
        for ($i = 0; $i < $nRows; $i++) {
            for ($j = 0; $j < $mCols; $j++) {
                $tab[$i][$j] = 0;
            }
        }
        $this->matrix[$id] = [
            'size' => [$nRows, $mCols],
            'tab' => $tab,
            'des' => $des,
        ];

        return $this;
    }

    /**
     * @param $id
     * @return mixed -все компоненты
     * ['size' => размер,'tab' => таблица, 'des' => описатель]
     */
    public function getMatr($id)
    {
        return $this->matrix[$id];
    }

    public function addRow($id, $iRow, $row)
    {
        if (!isset($this->matrix[$id]['tab'])) {
            return false;
        }
        $this->matrix[$id]['tab'][$iRow] = $row;
        return $this;
    }

    public function addCol($id, $jCol, $col)
    {
        if (!isset($this->matrix[$id]['tab'])) {
            return false;
        }
        $tab = $this->matrix[$id]['tab'];
        for ($i = 0; $i < sizeof($tab); $i++) {

            $tab[$i][$jCol] = $col[$i];
        }
        $this->matrix[$id]['tab'] = $tab;
        return $this;
    }

    public function scalarAdd($idFrom, $idTo, $x)
    {
        if (!isset($this->matrix[$idFrom]['tab'])) {
            return false;
        }
        $tab = $this->matrix[$idFrom]['tab'];
        $tabNew = [];
        for ($i = 0; $i < sizeof($tab); $i++) {
            $row = $tab[$i];
            for ($j = 0; $j < sizeof($tab[$i]); $j++) {
                $row[$j] += $x;
            }
            $tabNew[$i] = $row;
        }
        $nRow = $this->matrix[$idFrom]['size'][0];
        $mCol = $this->matrix[$idFrom]['size'][1];
        $desFrom = $this->matrix[$idFrom]['des'];
        $des = 'matr ' . $idFrom . '(' . $desFrom . ')  + ' . $x;
        $this->newMatr($idTo, $nRow, $mCol, $des);
        $this->matrix[$idTo]['tab'] = $tabNew;
        return $this;
    }

    public function scalarMult($idFrom, $idTo, $x)
    {
        if (!isset($this->matrix[$idFrom]['tab'])) {
            return false;
        }
        $tab = $this->matrix[$idFrom]['tab'];
        $tabNew = [];
        for ($i = 0; $i < sizeof($tab); $i++) {
            $row = $tab[$i];
            for ($j = 0; $j < sizeof($tab[$i]); $j++) {
                $row[$j] = $x * $row[$j];
            }
            $tabNew[$i] = $row;
        }
        $nRow = $this->matrix[$idFrom]['size'][0];
        $mCol = $this->matrix[$idFrom]['size'][1];
        $desFrom = $this->matrix[$idFrom]['des'];
        $des = 'matr ' . $idFrom . '(' . $desFrom . ')  * ' . $x;
        $this->newMatr($idTo, $nRow, $mCol, $des);
        $this->matrix[$idTo]['tab'] = $tabNew;
        return $this;

    }

    public function trans($idFrom, $idTo)
    {
        if (!isset($this->matrix[$idFrom]['tab'])) {
            return false;
        }
        $tab = $this->matrix[$idFrom]['tab'];
        $tabNew = [];
        for ($i = 0; $i < sizeof($tab); $i++) {
            $row = $tab[$i];
            for ($j = 0; $j < sizeof($tab[$i]); $j++) {
                $tabNew[$j][$i] = $row[$j];
            }
        }
        $nRow = $this->matrix[$idFrom]['size'][1];
        $mCol = $this->matrix[$idFrom]['size'][0];
        $desFrom = $this->matrix[$idFrom]['des'];
        $des = 'matr ' . $idFrom . '(' . $desFrom . ') T';
        $this->newMatr($idTo, $nRow, $mCol, $des);
        $this->matrix[$idTo]['tab'] = $tabNew;
        return $this;

    }

    /**
     * сложение матриц
     * @param $idA
     * @param $idB
     * @param $idC
     * @return $this|bool
     */
    public function matrAdd($idA, $idB, $idC)
    {
        if (!(isset($this->matrix[$idA]['tab']) &&
            isset($this->matrix[$idB]['tab']))) {
            return false;
        }
        $nRowA = $this->matrix[$idA]['size'][0];
        $mColA = $this->matrix[$idA]['size'][1];
        $nRowB = $this->matrix[$idB]['size'][0];
        $mColB = $this->matrix[$idB]['size'][1];

        if ($mColA !== $nRowB) {
            return false;
        }

        $tabA = $this->matrix[$idA]['tab'];
        $tabB = $this->matrix[$idB]['tab'];
        $tabNew = [];
        for ($i = 0; $i < sizeof($tabA); $i++) {
            $rowA = $tabA[$i];

            for ($jB = 0; $jB < $mColB; $jB++) {
                $eij = 0;
                for ($j = 0; $j < $mColA; $j++) {
                    $eij += $rowA[$j] * $tabB[$j][$jB];
                }
                $tabNew[$i][$jB] = $eij;

            }
        }
        $nRow = $this->matrix[$idA]['size'][0];
        $mCol = $this->matrix[$idB]['size'][1];
        $desA = $this->matrix[$idA]['des'];
        $desB = $this->matrix[$idA]['des'];
        $des = 'matr ' . $idA . '(' . $desA . ') * ' .
            $idB . '(' . $desB . ')';
        $this->newMatr($idC, $nRow, $mCol, $des);
        $this->matrix[$idC]['tab'] = $tabNew;
        return $this;


    }

    /**
     * Умножение матриц
     * @param $idA
     * @param $idB
     * @param $idC
     * @return $this|bool
     */
    public function matrMult($idA, $idB, $idC)
    {
        if (!(isset($this->matrix[$idA]['tab']) &&
            isset($this->matrix[$idB]['tab']))) {
            return false;
        }
        $nRowA = $this->matrix[$idA]['size'][0];
        $mColA = $this->matrix[$idA]['size'][1];
        $nRowB = $this->matrix[$idB]['size'][0];
        $mColB = $this->matrix[$idB]['size'][1];

        if ($mColA !== $nRowB) {
            return false;
        }

        $tabA = $this->matrix[$idA]['tab'];
        $tabB = $this->matrix[$idB]['tab'];
        $tabNew = [];
        for ($i = 0; $i < sizeof($tabA); $i++) {
            $rowA = $tabA[$i];
            for ($j = 0; $j < sizeof($mColB); $j++) {
                $e = 0;
                for ($k = 0; $k < $nRowB; $k++) {
                    $e += $rowA[$k] * $tabB[$k][$j];
                }
                $tabNew[$i][$j] = $e;
            }

        }
        $nRow = $this->matrix[$idA]['size'][0];
        $mCol = $this->matrix[$idB]['size'][1];
        $desA = $this->matrix[$idA]['des'];
        $desB = $this->matrix[$idA]['des'];
        $des = 'matr ' . $idA . '(' . $desA . ') * ' .
            $idB . '(' . $desB . ')';
        $this->newMatr($idC, $nRow, $mCol, $des);
        $this->matrix[$idC]['tab'] = $tabNew;
        return $this;

    }

    public function newVector($id, $coord, $polarFlag = false, $des = false)
    {
        if (false === $polarFlag) {
            $x = $coord[0];
            $y = $coord[1];
            $z = $coord[2];
            $this->setVect($x, $y, $z);
        } else {
            $phi = $coord[0];
            $theta = $coord[1];
            $r = $coord[2];
            $this->setPolar($phi, $theta, $r);
        }
        $nRow = 1;
        $mCol = 3;
        $this->newMatr($id, $nRow, $mCol, $des);
        $this->addRow($id, 0, $this->m_Vec);
        $idPolar = $id . $this->POLAR_ADD_ID;
        $this->newMatr($idPolar, $nRow, $mCol, $des . '-polar');

        $this->addRow($idPolar, 0, $this->m_polar);
        return $this;
    }

    public function getVector($id, $polarFlag = false)
    {
        $id = ($polarFlag) ? $id . $this->POLAR_ADD_ID : $id;
        return $this->matrix[$id]['tab'][0];
    }

    public function scalarProduct($idA, $idB)
    {
        $vA = $this->matrix[$idA]['tab'];
        $vB = $this->matrix[$idB]['tab'];
        $r = $vA[0] * $vB[0] + $vA[1] * $vB[1] + $vA[2] * $vB[2];
        return $r;
    }

    /**
     * векторное произведение
     * @param $idA
     * @param $idB
     * @param $idC
     * @return $this
     */
    public function vectorProduct($idA, $idB, $idC)
    {
        $a = $this->matrix[$idA]['tab'];
        $b = $this->matrix[$idB]['tab'];
        $x = 0;
        $y = 1;
        $z = 2;
        $coord = [$a[$y] * $b[$z] - $a[$z] * $b[$y],     // x
            -($a[$x] * $b[$z] - $a[$z] * $b[$x]),        // y
            $a[$x] * $b[$y] - $a[$y] * $b[$x],];       // z
        $desA = $this->matrix[$idA]['des'];
        $desB = $this->matrix[$idB]['des'];
        $des = 'vectProduct: ' . $idA . '(' . $desA . ') on ' .
            $idB . '(' . $desB . ')';
        $this->newVector($idC, $coord, $polarFlag = false, $des);
        return $this;
    }

    /**
     * матрица поворота вокруг оси ox
     * @param $phi
     */
    public function matrRx($phi)
    {
        $id = 'Rx';
        $this->newMatr($id, 3, 3, 'round oX');
        $cs = cos($phi);
        $sn = sin($phi);
        $tab = [];
        $tab[] = [1, 0, 0];
        $tab[] = [0, $cs, $sn];
        $tab[] = [0, -$sn, $cs];
        $this->matrix[$id]['tab'] = $tab;
        return $this;
    }

    /**
     * матрица поворота вокруг оси oy
     * @param $phi
     */
    public function matrRy($phi)
    {
        $id = 'Ry';
        $this->newMatr($id, 3, 3, 'round oY');
        $cs = cos($phi);
        $sn = sin($phi);
        $tab = [];
        $tab[] = [$cs, 0, -$sn];
        $tab[] = [0, 1, 0];
        $tab[] = [$sn, 0, $cs];
        $this->matrix[$id]['tab'] = $tab;
        return $this;
    }

    /**
     * матрица поворота вокруг оси oz
     * @param $phi
     */
    public function matrRz($phi)
    {
        $id = 'Rz';
        $this->newMatr($id, 3, 3, 'round oZ');
        $cs = cos($phi);
        $sn = sin($phi);
        $tab = [];
        $tab[] = [$cs, $sn, 0];
        $tab[] = [-$sn, $cs, 0];
        $tab[] = [0, 0, 1];
        $this->matrix[$id]['tab'] = $tab;
        return $this;
    }

}