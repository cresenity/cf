<?php

trait CElement_Component_DataTable_Trait_Legacy_ExportTrait {
    /**
     * @param string                             $filename
     * @param null|string                        $sheet_name
     * @param null|CElement_Component_DataTable  $table
     *
     * @return void
     */
    private static function exportExcelxmlStatic($filename, $sheet_name = null, $table = null) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo '
                <html xmlns:o="urn:schemas-microsoft-com:office:office"
                        xmlns:x="urn:schemas-microsoft-com:office:excel"
                        xmlns="http://www.w3.org/TR/REC-html40">
                        <head>
                        <meta http-equiv=Content-Type content="text/html; charset=us-ascii">
                        </meta><meta name=ProgId content=Excel.Sheet>
                        </meta><meta name=Generator content="Microsoft Excel 11">
                        <link rel=File-List href="data.xls_files/filelist.xml">
                        </link><link rel=Edit-Time-Data href="data.xls_files/editdata.mso">
                        </link><link rel=OLE-Object-Data href="data.xls_files/oledata.mso">
                        </link></meta>
                        <style>

                        table {
                            mso-displayed-decimal-separator:"\.";
                            mso-displayed-thousand-separator:"\,";
                        }
                        col{
                            mso-width-source:auto;
                        }
                        td{
                                font-size:8pt;
                                font-family:Arial;

                        }
                        th{
                            font-size:8pt;font-family:Arial;
                        }
                        table thead th {
                             background:#000;
                            border-top:.5pt solid #545454;
                            border-left:.5pt solid #545454;
                            border-right:.5pt solid #545454;
                            border-bottom:.5pt solid #545454;
                            color:#fff;
                        }
                        @Page{
                            mso-header-data:"&L&BInternational&B&C&BAsia Pacific&B&R&BPage &P&B";
                            mso-footer-data:"&L&022Arial022Asia Pacific&R2011-09-08";
                            margin:1.0in .75in 1.0in .75in;
                            mso-header-margin:.5in;
                            mso-footer-margin:.5in;
                        }
                        table.data th.thead{
                          background:#000;
                          border-top:.5pt solid #545454;
                          border-left:.5pt solid #545454;
                          border-right:.5pt solid #545454;
                          border-bottom:.5pt solid #545454;
                          color:#fff;
                        }

                        .tfoot{
                          background:#000;
                          border-top:.5pt solid #545454;
                          border-left:.5pt solid #545454;
                          border-right:.5pt solid #545454;
                          border-bottom:.5pt solid #545454;
                          color:#fff;
                          font-weight:bold;
                        }



                        .odd{background:#f7f7f7;}
                        .even{background:#e7e7e7;}

                        table.data td {
                                border-top:.5pt solid #545454;
                                border-left:.5pt solid #545454;
                                border-right:.5pt solid #545454;
                                border-bottom:.5pt solid #545454;
                        }



                        .align-left {text-align:left;}
                        .align-right {text-align:right;}
                        .align-center {text-align:center;}

                        </style>
                        </head>
                        <body>';
        echo '<table class="data table table-responsive table-bordered table-striped responsive" id="' . $table->id . '">';
        echo '<thead>';

        $header_count = count($table->report_header);
        $totalColumn = count($table->columns);
        $additionColumn = 0;
        if ($table->numbering) {
            $additionColumn++;
        }
        for ($ii = 1; $ii <= $header_count; $ii++) {
            echo '<tr><td colspan="' . ($totalColumn + $additionColumn) . '">' . $table->report_header[$ii - 1] . '</td></tr>';
        }
        if (strlen($table->customColumnHeader) > 0) {
            echo $table->customColumnHeader;
        } else {
            echo '<tr>';

            if ($table->numbering) {
                echo '<th class="align-right thead" data-align="align-right" width="20" scope="col">No</th>';
            }

            foreach ($table->columns as $col) {
                echo $col->render_header_html($table->export_pdf, '', 0);
            }
            echo '</tr>';
        }
        echo '</thead>';
        echo '<tbody>';
        $no = 0;
        $data = $table->data;
        if (!is_resource($data)) {
            $data = c::db()->query($table->query);
        }
        if (!is_resource($data)) {
            if (is_object($data)) {
                $data = $data->resultArray(false);
            }
        }
        foreach ($data as $row) {
            $no++;
            $key = '';

            if (array_key_exists($table->keyField, $row)) {
                $key = $row[$table->keyField];
            }
            $class = '';
            if ($no % 2 == 0) {
                $class .= ' even';
            } else {
                $class .= ' odd';
            }
            echo '<tr class="' . $class . '" id="tr-' . $key . '">';

            if ($table->numbering) {
                echo '<td scope="row" class="align-right">' . $no . '</td>';
            }

            $js = '';
            $jsparam = [];
            foreach ($table->columns as $col) {
                $col_found = false;
                $new_v = '';
                $col_v = '';
                $ori_v = '';
                //do print from query
                foreach ($row as $k => $v) {
                    if ($k == $col->get_fieldname()) {
                        $col_v = $v;
                        $ori_v = $col_v;
                        $col_v = $col->applyTransform($col_v, $row);
                    }
                }
                //if formatted
                if (strlen($col->format) > 0) {
                    $temp_v = $col->format;
                    foreach ($row as $k2 => $v2) {
                        if (strpos($temp_v, '{' . $k2 . '}') !== false) {
                            $temp_v = str_replace('{' . $k2 . '}', $v2, $temp_v);
                        }
                        $col_v = $temp_v;
                    }
                }

                $new_v = $col_v;

                if (($table->cellCallbackFunc) != null) {
                    $new_v = CFunction::factory($table->cellCallbackFunc)
                        ->addArg($table)
                        ->addArg($col->get_fieldname())
                        ->addArg($row)
                        ->addArg($new_v)
                        ->setRequire($table->requires)
                        ->execute();

                    if (is_array($new_v) && isset($new_v['html'], $new_v['js'])) {
                        $new_v = carr::get($new_v, 'html');
                        $js .= carr::get($new_v, 'js');
                    }
                    //call_user_func($table->cellCallbackFunc,$table,$col->get_fieldname(),$row,$v);
                }
                $class = '';
                switch ($col->getAlign()) {
                    case CConstant::ALIGN_LEFT:
                        $class .= ' align-left';

                        break;
                    case CConstant::ALIGN_RIGHT:
                        $class .= ' align-right';

                        break;
                    case CConstant::ALIGN_CENTER:
                        $class .= ' align-center';

                        break;
                }
                if ($no % 2 == 0) {
                    $class .= ' even';
                } else {
                    $class .= ' odd';
                }
                echo '<td class="' . $class . '" data-column="' . $col->get_fieldname() . '">' . $new_v . '</td>';

                $col_found = true;
            }
            echo '</tr>';
        }
        echo '</tbody>';
        if ($table->footer) {
            echo '<tfoot>';

            $totalColumn = count($table->columns);
            $additionColumn = 0;
            if ($table->numbering) {
                $additionColumn++;
            }

            foreach ($table->footerFields as $f) {
                echo '<tr>';

                $colspan = $f->getLabelColSpan();
                if ($colspan == 0) {
                    $colspan = $totalColumn + $additionColumn - 1;
                }
                echo '<td class="tfoot" colspan="' . ($colspan) . '">';
                echo $f->getLabel();
                echo '</td>';
                $class = '';
                switch ($f->getAlign()) {
                    case 'left':
                        $class .= ' align-left';

                        break;
                    case 'right':
                        $class .= ' align-right';

                        break;
                    case 'center':
                        $class .= ' align-center';

                        break;
                }

                $fval = $f->getValue();

                if (is_array($fval)) {
                    $skip_column = 0;

                    foreach ($table->columns as $col) {
                        $is_skipped = false;
                        if ($skip_column < $colspan) {
                            $skip_column++;
                            $is_skipped = true;
                        }
                        if (!$is_skipped) {
                            $fcolval = '';
                            if (isset($fval[$col->get_fieldname()])) {
                                $fcolval = $fval[$col->get_fieldname()];
                            }

                            switch ($col->get_align()) {
                                case 'left':
                                    $class .= ' align-left';

                                    break;
                                case 'right':
                                    $class .= ' align-right';

                                    break;
                                case 'center':
                                    $class .= ' align-center';

                                    break;
                            }
                            echo '<td class="tfoot ' . $class . '">';
                            echo $fcolval;
                            echo '</td>';
                        }
                    }
                } else {
                    echo '<td class="tfoot ' . $class . '">';
                    echo $fval;
                    echo '</td>';
                }
                echo '</tr>';
            }
            echo '</tfoot>';
        }
        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit;
    }
}
