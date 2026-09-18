<?php

/**
 * Description of TableRenderer
 */
class CProfiler_TableRenderer {
    protected $columns = [];
    protected $rows = [];

    /**
     * Add column to table.
     *
     * @param mixed $class
     * @param mixed $style
     */
    public function addColumn($class = '', $style = '') {
        $this->columns[] = ['class' => $class, 'style' => $style];
    }

    /**
     * Add row to table.
     *
     * @param mixed $data  data to go in table cells
     * @param mixed $class CSS class
     * @param mixed $style CSS style
     */
    public function addRow($data, $class = '', $style = '') {
        $this->rows[] = ['data' => $data, 'class' => $class, 'style' => $style];
    }

    /**
     * Render table.
     *
     * @return string
     */
    public function render() {
        $rows = $this->rows;
        $columns = $this->columns;

        $html = '';
        $html .= '<table class="kp-table">';
        foreach ($rows as $row) {
            $class = empty($row['class']) ? '' : ' class="' . $row['class'] . '"';
            $style = empty($row['style']) ? '' : ' style="' . $row['style'] . '"';
            $html .= '<tr' . $class . $style . '>';
            foreach ($columns as $index => $column) {
                $class = empty($column['class']) ? '' : ' class="' . $column['class'] . '"';
                $style = empty($column['style']) ? '' : ' style="' . $column['style'] . '"';
                $value = $row['data'][$index];
                $value = (is_array($value) or is_object($value)) ? '<pre>' . c::e(print_r($value, true)) . '</pre>' : c::e($value);
                $html .= '<td' . $style . $class . '>' . $value . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }
}
