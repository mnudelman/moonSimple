<?php


class Report extends Common
{
    private $title;             // заголовок
    private $cap = [];          // шапка таблицы
    private $row = [];

    //------------------------------------------//
    protected function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    protected function setCap($cap)
    {
        $this->cap = $cap;
        return $this ;
    }

    protected function setCell($key, $value)
    {
        if (in_array($key, $this->cap)) {
            $this->row[$key] = $value;
        }
        return $this ;
    }
    protected function rowEmpty() {
        $this->row = [] ;
    }
    protected function begTab()
    {
        echo '<table>';
        $this->titleOut();
        $this->capOut();
    }

    protected function endTab()
    {
        echo '</table>';
    }
    protected function titleOut() {
        echo '<caption>';
        echo $this->title  ;
        echo '</caption>';
    }
    protected function capOut()
    {
        echo '<tr>';
        for ($i = 0; $i < sizeof($this->cap); $i++) {
            echo '<th>';
            echo $this->cap[$i];
            echo '</th>';
        }
        echo '</tr>';
    }

    protected function rowOut()
    {
        echo '<tr>';
        for ($i = 0; $i < sizeof($this->cap); $i++) {
            $key = $this->cap[$i];
            $val = (isset($this->row[$key])) ? $this->row[$key] : '';
            echo '<td>';
            echo $val;
            echo '</td>';
        }
        echo '</tr>';
    }
}