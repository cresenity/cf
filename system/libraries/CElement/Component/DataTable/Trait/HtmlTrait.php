<?php

trait CElement_Component_DataTable_Trait_HtmlTrait {
    /**
     * @return void
     */
    protected function htmlGetTableClass() {
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function html($indent = 0) {
        /** @var CElement_Component_DataTable $this */
        $this->buildOnce();
        $html = new CStringBuilder();
        $html->setIndent($indent);

        if ($this->haveRowAction()) {
            if ($this->getRowActionStyle() == 'btn-dropdown') {
                if ($this->actionLocation == 'first') {
                    $this->getRowActionList()->addClass('dropdown-menu-left');
                } else {
                    $this->getRowActionList()->addClass('dropdown-menu-right');
                }
            }
        }

        $wrapped = $this->applyDataTable || $this->haveHeaderAction() || $this->haveFooterAction() || strlen($this->title) > 0;
        $classes = $this->classes;
        $tableClass = is_array($classes) ? implode(' ', $classes) : '';
        $custom_css = $this->custom_css;
        $custom_css = $this->renderStyle($custom_css);
        if (strlen($custom_css) > 0) {
            $custom_css = ' style="' . $custom_css . '"';
        }
        if ($wrapped) {
            $widgetWrapperClass = c::theme('widget.class.wrapper', 'widget-box');
            $widgetHeaderClass = c::theme('widget.class.header', 'widget-title');
            $widgetBodylass = c::theme('widget.class.body', 'widget-content');
            $mainClass = ' ' . $widgetWrapperClass . ' ' . $tableClass . ' ';
            $mainClassTitle = ' ' . $widgetHeaderClass . ' ';
            $tableViewClass = $this->dataTableView == CConstant::TABLE_VIEW_COL ? ' data-table-col-view' : ' data-table-row-view';
            $mainClassContent = ' ' . $widgetBodylass . ' ' . $tableViewClass . ' col-view-count-' . $this->dataTableViewColCount;

            if ($this->widget_title == false) {
                $mainClassTitle = ' ';
            }
            if ($this->haveDataTableViewAction) {
                $mainClassTitle .= ' with-elements';
            }
            $html->appendln('<div id="' . $this->id() . '-widget-box" class="' . $mainClass . ' widget-table" ' . $custom_css . '>')->incIndent();
            $showTitle = (strlen($this->title) > 0) || $this->haveHeaderAction();
            if ($showTitle) {
                $html->appendln('<div class="' . $mainClassTitle . '">')->incIndent();
                if (strlen($this->icon) > 0) {
                    $html->appendln('<span class="icon">')->incIndent();
                    $html->append($this->getIconHtml());
                    // $html->appendln('<i class="icon-' . $this->icon . '"></i>');
                    $html->decIndent()->appendln('</span');
                }
                $html->appendln('<h5>' . $this->title . '</h5>');

                if ($this->haveHeaderAction()) {
                    $html->appendln($this->getHeaderActionList()->html($html->getIndent()));

                    $this->js_cell .= $this->getHeaderActionList()->js();
                }

                if ($this->haveDataTableViewAction) {
                    $colViewActionActive = $this->dataTableView == CConstant::TABLE_VIEW_COL ? ' active' : '';
                    $rowViewActionActive = $this->dataTableView == CConstant::TABLE_VIEW_ROW ? ' active' : '';
                    $colViewActionChecked = $this->dataTableView == CConstant::TABLE_VIEW_COL ? ' checked="checked"' : '';
                    $rowViewActionChecked = $this->dataTableView == CConstant::TABLE_VIEW_ROW ? ' checked="checked"' : '';
                    $html->appendln('
                        <div class="btn-group btn-group-toggle ml-auto" data-toggle="buttons">
                            <label class="btn btn-default icon-btn md-btn-flat ' . $colViewActionActive . '">
                                <input type="radio" name="' . $this->id() . '-data-table-view" value="data-table-col-view" ' . $colViewActionChecked . ' />
                                <span class="ion ion-md-apps"></span>
                            </label>
                            <label class="btn btn-default icon-btn md-btn-flat ' . $rowViewActionActive . '">
                                <input type="radio" name="' . $this->id() . '-data-table-view" value="data-table-row-view" ' . $rowViewActionChecked . '" />
                                <span class="ion ion-md-menu"></span>
                            </label>
                        </div>
                    ');
                }
                $html->decIndent()->appendln('</div>');
            }
            $nopaddingClass = $this->applyDataTable ? '' : 'nopadding';
            $html->appendln('<div class="' . $mainClassContent . ' ' . $nopaddingClass . '">')->incIndent();
        }

        $html->append($this->rawHtml($html->getIndent()), $wrapped);
        if ($wrapped) {
            $html->decIndent()->appendln('</div>');
            if ($this->haveFooterAction() || strlen($this->footerTitle) > 0) {
                $mainFooterClass = 'widget-footer';
                if ($this->haveFooterAction()) {
                    $mainFooterClass .= ' with-elements';
                }
                $html->appendln('<div class="' . $mainFooterClass . '">')->incIndent();
                if (strlen(strlen($this->footerTitle) > 0)) {
                    $html->appendln('<h5>' . $this->footerTitle . '</h5>');
                }
                if ($this->haveFooterAction()) {
                    $html->appendln('<div class="widget-footer-elements ml-auto">')->incIndent();
                    $html->appendln($this->getFooterActionList()->html($html->getIndent()));

                    $this->js_cell .= $this->getFooterActionList()->js();
                    $html->decIndent()->appendln('</div>');
                }
                $html->decIndent()->appendln('</div>');
            }
            $html->decIndent()->appendln('</div>');
        }

        return $html->text();
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    protected function rawTBody($indent = 0) {
        /** @var CElement_Component_DataTable $this */
        $html = new CStringBuilder();
        $html->setIndent($indent);

        $tbodyId = (strlen($this->tbodyId) > 0 ? "id='" . $this->tbodyId . "' " : '');
        $js = '';
        $html->appendln('<tbody ' . $tbodyId . '>')->incIndent()->br();
        //render body;
        $html->appendln($this->htmlChild($indent));
        $no = 0;
        if (!$this->ajax) {
            if ($this->query instanceof CManager_Contract_DataProviderInterface) {
                $this->data = $this->query->toEnumerable();
            }
            if (is_array($this->data) || $this->data instanceof Traversable || $this->data instanceof CInterface_Enumerable) {
                foreach ($this->data as $row) {
                    if ($row == null) {
                        continue;
                    }
                    if ($row instanceof CRenderable) {
                        $html->appendln($row->html());

                        continue;
                    }
                    $dataRow = new CElement_Component_DataTable_DataRow($row);
                    $rowArray = $dataRow->toArray();

                    $no++;
                    $key = '';

                    if ($dataRow->exists($this->keyField)) {
                        $key = $dataRow->getValue($this->keyField);
                    }

                    $trClass = '';
                    if ($this->rowClassCallbackFunction != null) {
                        $trClass = CFunction::factory($this->rowClassCallbackFunction)
                            ->addArg($row)
                            ->execute();
                    }
                    $attrId = '';
                    if ($key) {
                        $attrId = 'id="tr-' . $key . '"';
                    }
                    $html->appendln('<tr ' . $attrId . ($trClass ? ' class="' . $trClass . '"' : '') . '>')->incIndent()->br();

                    if ($this->numbering) {
                        $html->appendln('<td scope="row" class="align-right">' . $no . '</td>')->br();
                    }
                    if ($this->checkbox) {
                        $checkboxHtml = $this->callCheckboxRenderer($row);
                        $html->appendln('
                            <td scope="row" class="checkbox-cell align-center">
                                ' . $checkboxHtml . '
                            </td>
                        ')->br();
                    }

                    if ($this->actionLocation == 'first') {
                        $js .= $this->drawActionAndGetJs($html, $dataRow, $key);
                    }
                    foreach ($this->columns as $col) {
                        /** @var CElement_Component_DataTable_Column $col */
                        if (!$this->applyDataTable && !$col->isVisible()) {
                            continue;
                        }
                        $cell = new CElement_Component_DataTable_Cell($this, $col, $row);

                        $newValue = $cell->html();
                        $js .= $cell->js();

                        $class = $col->getClassAttribute();
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
                        if ($col->getNoLineBreak()) {
                            $class .= ' no-line-break';
                        }
                        if ($col->getHiddenPhone()) {
                            $class .= ' hidden-phone';
                        }

                        if ($col->getHiddenTablet()) {
                            $class .= ' hidden-tablet';
                        }

                        if ($col->getHiddenDesktop()) {
                            $class .= ' hidden-desktop';
                        }

                        $pdfTBodyTdCurrentAttr = $this->getPdfTBodyTdAttr();
                        if ($this->export_pdf) {
                            switch ($col->getAlign()) {
                                case 'left':
                                    $pdfTBodyTdCurrentAttr .= ' align="left"';

                                    break;
                                case 'right':
                                    $pdfTBodyTdCurrentAttr .= ' align="right"';

                                    break;
                                case 'center':
                                    $pdfTBodyTdCurrentAttr .= ' align="center"';

                                    break;
                            }
                        }
                        $styleAttribute = 'style="' . $col->getCssStyle() . '"';
                        $html->appendln('<td' . $pdfTBodyTdCurrentAttr . ' class="' . $class . '" data-column="' . $col->getFieldname() . '" ' . $styleAttribute . '>' . $newValue . '</td>')->br();
                    }
                    if ($this->actionLocation == 'last') {
                        $js .= $this->drawActionAndGetJs($html, $dataRow, $key);
                    }

                    $html->decIndent()->appendln('</tr>')->br();
                }
            }
            // hanya tanpa DataTables: DataTables membaca baris colspan ini sebagai data
            // (1 sel vs N kolom -> crash _DT_CellIndex) dan sudah punya pesan kosong sendiri
            if ($no === 0 && !$this->applyDataTable) {
                $emptyColspan = count($this->columns);
                if ($this->haveRowAction()) {
                    $emptyColspan++;
                }
                if ($this->numbering) {
                    $emptyColspan++;
                }
                if ($this->checkbox) {
                    $emptyColspan++;
                }
                $emptyMessage = carr::get($this->labels, 'noData', carr::get($this->labels, 'emptyTable'));
                $html->appendln('<tr class="odd">')->incIndent()->br();
                $html->appendln('<td colspan="' . $emptyColspan . '" class="text-center dataTables_empty">' . $emptyMessage . '</td>')->br();
                $html->decIndent()->appendln('</tr>')->br();
            }
        }

        $this->js_cell .= $js;

        $html->decIndent()->appendln('</tbody>')->br();

        return $html->text();
    }

    /**
     * @param CStringBuilder                       $html
     * @param CElement_Component_DataTable_DataRow $row
     * @param int|string                           $key
     *
     * @return string
     */
    protected function drawActionAndGetJs(CStringBuilder $html, CElement_Component_DataTable_DataRow $row, $key) {
        $js = '';
        if ($this->haveRowAction()) {
            $html->appendln('<td class="low-padding align-center cell-action td-action">')->incIndent()->br();
            foreach ($row->toArray() as $k => $v) {
                $jsparam[$k] = $v;
            }

            $jsparam['param1'] = $key;

            $this->getRowActionList()->regenerateId(true);
            $this->getRowActionList()->apply('setJsParam', $jsparam);
            $this->getRowActionList()->apply('setHandlerParam', $jsparam);
            $actions = $this->getRowActionList()->childs();
            $actionNeedRender = false;
            foreach ($actions as &$action) {
                /** @var CElement_Component_ActionRow $action */
                if (($this->filterActionCallbackFunc) != null) {
                    $visibility = CFunction::factory($this->filterActionCallbackFunc)
                        ->addArg($this)
                        ->addArg('action')
                        ->addArg($row->toArray())
                        ->addArg($action)
                        ->setRequire($this->requires)
                        ->execute();
                    if ($visibility == false) {
                        $action->addClass('d-none');
                    }
                    $action->setVisibility($visibility);
                }
                if ($action instanceof CElement_Component_ActionRow) {
                    $action->applyRowCallback($row->getRow());
                }
                $actionNeedRender = $actionNeedRender || $action->isVisible();
            }

            $js = $this->getRowActionList()->js();
            if ($actionNeedRender) {
                $html->appendln($this->getRowActionList()->html($html->getIndent()));
            }
            $html->decIndent()->appendln('</td>')->br();
        }

        return $js;
    }

    /**
     * @param int  $indent
     * @param bool $wrapped
     *
     * @return string
     */
    protected function rawHtml($indent = 0, $wrapped = false) {
        $html = new CStringBuilder();
        $html->setIndent($indent);
        /** @var CElement_Component_DataTable $this */
        $thClass = '';
        if ($this->headerNoLineBreak) {
            $thClass = ' no-line-break';
        }
        $defaultOverflow = 'auto';
        if ($this->applyDataTable) {
            $defaultOverflow = 'visible';
        }
        $htmlResponsiveOpen = '<div class="table-responsive" style="overflow: ' . $defaultOverflow . ';">';
        $htmlResponsiveClose = '</div>';
        if ($this->responsive) {
            $htmlResponsiveOpen = '<div class="span12" style="overflow: auto;margin-left: 0;">';
            $htmlResponsiveClose = '</div>';
        }

        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }
        if ($this->tableStriped) {
            $classes .= ' table-striped ';
        }
        if ($this->tableBordered) {
            $classes .= ' table-bordered ';
        }

        $html->appendln($htmlResponsiveOpen . '<table ' . $this->getPdfTableAttr() . ' class="table responsive' . $classes . '" id="' . $this->id . '">')
            ->incIndent()->br();
        if ($this->show_header) {
            $html->append($this->htmlTHead());
        }

        $html->append($this->rawTBody($html->getIndent()));

        //footer
        if ($this->footer) {
            $html->incIndent()->appendln('<tfoot>')->br();
            $total_column = count($this->columns);
            $addition_column = 0;
            if ($this->haveRowAction()) {
                $addition_column++;
            }
            if ($this->numbering) {
                $addition_column++;
            }
            if ($this->checkbox) {
                $addition_column++;
            }

            foreach ($this->getFooterFields() as $footerField) {
                $html->incIndent()->appendln('<tr>')->br();

                $colspan = $footerField->getLabelColSpan();
                if ($colspan == 0) {
                    $colspan = $total_column + $addition_column - 1;
                }
                $html->incIndent()->appendln('<td colspan="' . ($colspan) . '">')->br();
                $html->appendln($footerField->getLabel())->br();
                $html->decIndent()->appendln('</td>')->br();
                $class = '';
                switch ($footerField->getAlign()) {
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

                $fval = $footerField->getValue();
                if ($fval instanceof CRenderable) {
                    $html->incIndent()->appendln('<td class="' . $class . '">')->br();
                    list($cellHtml, $cellJs) = $this->getHtmlJsCell($fval);
                    $html->appendln($fval->html($indent))->br();
                    $html->decIndent()->appendln('</td>')->br();
                } elseif (is_array($fval)) {
                    $skip_column = 0;

                    foreach ($this->columns as $col) {
                        $is_skipped = false;
                        if ($skip_column < $colspan) {
                            $skip_column++;
                            $is_skipped = true;
                        }
                        if (!$is_skipped) {
                            $fcolval = '';
                            if (isset($fval[$col->getFieldname()])) {
                                $fcolval = $fval[$col->getFieldname()];
                            }
                            $class = '';
                            switch ($col->getAlign()) {
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
                            $html->incIndent()->appendln('<td class="' . $class . '">')->br();
                            $html->appendln($fcolval)->br();
                            $html->decIndent()->appendln('</td>')->br();
                        }
                    }
                } else {
                    $html->incIndent()->appendln('<td class="' . $class . '">')->br();
                    $html->appendln($fval)->br();
                    $html->decIndent()->appendln('</td>')->br();
                }
                $html->decIndent()->appendln('</tr>')->br();
            }
            $html->decIndent()->appendln('</tfoot>')->br();
        }
        $html->decIndent()->appendln('</table>' . $htmlResponsiveClose);

        return $html->text();
    }

    /**
     * @return string
     */
    public function htmlTHead() {
        $thClass = '';
        /** @var CElement_Component_DataTable $this */
        if ($this->headerNoLineBreak) {
            $thClass = ' no-line-break';
        }
        $html = new CStringBuilder();
        $html->appendln('<thead>')
            ->incIndent()->br();
        if (strlen($this->customColumnHeader) > 0) {
            $html->appendln($this->customColumnHeader);
        } else {
            $html->appendln('<tr>')
                ->incIndent()->br();

            if ($this->numbering) {
                $html->appendln('<th data-align="align-right" class="' . $thClass . '" width="20" scope="col">No</th>')->br();
            }
            if ($this->checkbox) {
                $attrWidth = '';
                if (strlen($this->checkboxColumnWidth) > 0) {
                    $attrWidth = 'width="' . $this->checkboxColumnWidth . '"';
                }
                $html->appendln('
                    <th class="align-center" data-align="align-center" class="' . $thClass . '" scope="col" ' . $attrWidth . '>
                        <div class="capp-table-checkbox-wrapper">
                            <input type="checkbox" name="' . $this->id . '-check-all" id="' . $this->id . '-check-all" class="' . $this->id . '-check-all" value="1">
                            <label for="' . $this->id . '-check-all"></label>
                        </div>
                    </th>')->br();
            }
            if ($this->getActionLocation() == 'first') {
                $html->appendln($this->htmlActionTh());
            }
            foreach ($this->columns as $col) {
                /** @var CElement_Component_DataTable_Column $col */
                if (!$this->applyDataTable && !$col->isVisible()) {
                    continue;
                }

                $html->appendln($col->renderHeaderHtml($this->export_pdf, $thClass, $html->getIndent()))->br();
            }

            if ($this->getActionLocation() == 'last') {
                $html->appendln($this->htmlActionTh());
            }
            $html->decIndent()->appendln('</tr>')->br();
        }
        $html->decIndent()->appendln('</thead>')->br();

        return $html->text();
    }

    /**
     * @return string
     */
    public function htmlActionTh() {
        $thClass = '';
        if ($this->headerNoLineBreak) {
            $thClass = ' no-line-break';
        }
        $html = '';
        if ($this->haveRowAction()) {
            $actionWidth = 31 * $this->rowActionCount() + 5;
            if ($this->getRowActionStyle() == 'btn-dropdown') {
                $actionWidth = 70;
            }
            $html = '<th data-action="cell-action td-action" data-align="align-center" scope="col" width="' . $actionWidth . '" class="align-center cell-action th-action' . $thClass . '">' . c::__($this->actionHeaderLabel) . '</th>';
        }

        return $html;
    }

    /**
     * @param mixed $cell
     *
     * @return array
     */
    protected function getHtmlJsCell($cell) {
        $html = '';
        $js = '';

        if (is_string($cell)) {
            $html = $cell;
        }

        if ($cell instanceof CRenderable) {
            $html = $cell->html();
            $js = $cell->js();
        }

        if (carr::accessible($cell)) {
            $html = carr::get($cell, 'html');
            $js = carr::get($cell, 'js');
        }

        return [$html, $js];
    }
}
