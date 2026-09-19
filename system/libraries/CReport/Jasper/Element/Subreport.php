<?php

class CReport_Jasper_Element_Subreport extends CReport_Jasper_Element {
    public $returnValues;

    public function generate(CReport_Jasper_Report $report) {
        $this->returnValues = [];
        $row = $report->getCurrentRow();

        $print_expression_result = false;
        $printWhenExpression = (string) $this->xmlElement->reportElement->printWhenExpression;
        if ($printWhenExpression != '') {
            $printWhenExpression = $report->getExpression($printWhenExpression, $row);
            eval('if(' . $printWhenExpression . '){$print_expression_result=true;}');
        } else {
            $print_expression_result = true;
        }
        if ($print_expression_result !== true) {
            return;
        }

        $xmlFile = (string) $this->xmlElement->subreportExpression;
        $xmlFile = str_ireplace(['"'], [''], $xmlFile);
        //$rowArray =is_array($row)?$row:get_object_vars($row);
        $parameters = $report->getParameterCollection()->getList();
        $rowArray = [];
        if ($row) {
            $rowArray = $row->toArray();
        }

        $newParameters = array_merge($parameters, $rowArray);
        //$GLOBALS['reports'][$xmlFile] = (array_key_exists($xmlFile, $GLOBALS['reports'])) ? $GLOBALS['reports'][$xmlFile] : new JasperPHP\Report($xmlFile);
        $subreport = new CReport_Jasper_Report($xmlFile, $newParameters); //$GLOBALS['reports'][$xmlFile];
        //$this->children= array($report);

        if (preg_match('#^\\$F{#', $this->xmlElement->dataSourceExpression) === 1) {
            $subreport->dbData = $report->getExpression($this->xmlElement->dataSourceExpression, $row, null);
        }

        $subreport->getRoot()->generate($report);
        foreach ($this->xmlElement->returnValue as $r) {
            $this->returnValues[] = $r;
        }
        $report->setReturnVariables($this, $subreport->arrayVariable);
    }
}
