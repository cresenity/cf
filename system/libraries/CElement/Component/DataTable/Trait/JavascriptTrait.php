<?php

trait CElement_Component_DataTable_Trait_JavascriptTrait {
    /**
     * @param int $indent
     *
     * @return string
     */
    public function js($indent = 0) {
        $quickSearchPlaceholder = $this->quickSearchPlaceholder ? "'" . $this->quickSearchPlaceholder . "'" : "'" . c::__('element/datatable.search') . " ' + title";

        /** @var CElement_Component_DataTable $this */
        $this->buildOnce();
        $ajaxUrl = '';
        $varNameOTable = null;
        if ($this->ajax) {
            $columns = [];
            foreach ($this->columns as $col) {
                $columns[] = $col;
            }

            $isModelQuery = $this->query instanceof CModel_Query;
            if ($isModelQuery) {
                $this->query = CModel_QuerySerializer::serialize($this->query);
            }

            $ajaxMethod = CAjax::createMethod();
            $ajaxMethod->setType('DataTable');
            $ajaxMethod->setData('columns', $columns);
            $ajaxMethod->setData('query', $this->query);
            $ajaxMethod->setData('isModelQuery', $isModelQuery);
            $ajaxMethod->setData('isDataProvider', $this->query instanceof CManager_Contract_DataProviderInterface);

            $table = $this->getForAjaxSerialization();

            $ajaxMethod->setData('table', serialize($table));

            $ajaxMethod->setData('dbConfig', $this->dbConfig);
            $ajaxMethod->setData('dbName', $this->dbName);
            $ajaxMethod->setData('actionLocation', $this->actionLocation);
            $ajaxMethod->setData('checkbox', $this->checkbox);
            $ajaxMethod->setData('isElastic', $this->isElastic);
            $ajaxMethod->setData('isCallback', $this->isCallback);
            $ajaxMethod->setData('callbackRequire', $this->callbackRequire);
            $ajaxMethod->setData('callbackOptions', $this->callbackOptions);
            if (c::app()->isAuthEnabled()) {
                $ajaxMethod->enableAuth();
            }
            $ajaxUrl = $ajaxMethod->makeUrl();
        }

        $js = new CStringBuilder();

        $js->setIndent($indent);

        $totalColumn = count($this->columns);
        if ($this->haveRowAction()) {
            $totalColumn++;
        }
        if ($this->checkbox) {
            $totalColumn++;
        }
        if ($this->numbering) {
            $totalColumn++;
        }

        if ($this->applyDataTable) {
            $km = '';
            $vm = '';
            foreach ($this->paging_list as $k => $v) {
                if (strlen($km) > 0) {
                    $km .= ', ';
                }
                if (strlen($vm) > 0) {
                    $vm .= ', ';
                }
                $km .= $k;
                $vm .= "'" . $v . "'";
            }
            $hsVal = $this->headerSortable ? 'true' : 'false';
            $varName = 'table_' . cstr::slug($this->id(), '_');
            $varNameOTable = 'otable_' . cstr::slug($this->id(), '_');
            $js->appendln('var ' . $varName . " = jQuery('#" . $this->id . "');")->br();
            $js->appendln('var header_sortable = ' . $hsVal . ';')->br();
            $js->appendln('var vaoColumns = [];')->br();
            if ($this->numbering) {
                $aojson = [];
                $aojson['bSortable'] = false;
                $aojson['bSearchable'] = false;
                $aojson['bVisible'] = true;
                $js->appendln('vaoColumns.push( ' . json_encode($aojson) . ' );')->br();
            }
            if ($this->checkbox) {
                $aojson = [];
                $aojson['bSortable'] = false;
                $aojson['bSearchable'] = false;
                $aojson['bVisible'] = true;
                $js->appendln('vaoColumns.push( ' . json_encode($aojson) . ' );')->br();
            }

            if ($this->haveRowAction() && $this->actionLocation != 'last') {
                $aojson = [];
                $aojson['bSortable'] = false;
                $aojson['bSearchable'] = false;
                $aojson['bVisible'] = true;
                $js->appendln('vaoColumns.push( ' . json_encode($aojson) . ' );')->br();
            }
            foreach ($this->columns as $col) {
                $aojson = [];
                $aojson['bSortable'] = $col->sortable && $this->headerSortable;
                $aojson['bSearchable'] = $col->searchable;
                $aojson['bVisible'] = $col->visible;

                $js->appendln('vaoColumns.push( ' . json_encode($aojson) . ' );');
            }
            if ($this->haveRowAction() && $this->actionLocation == 'last') {
                $aojson = [];
                $aojson['bSortable'] = false;
                $aojson['bSearchable'] = false;
                $aojson['bVisible'] = true;
                $js->appendln('vaoColumns.push( ' . json_encode($aojson) . ' );')->br();
            }

            $js->appendln('var tableStyled_' . $this->id . ' = false;')->br()
                ->appendln('if ($.fn.DataTable.isDataTable(' . $varName . ')) { ' . $varName . '.DataTable().destroy(); }')->br()
                ->appendln('window.' . $varNameOTable . ' = ' . $varName . '.dataTable({')->br()->incIndent();

            //   $js->appendln("responsive: {
            //        details: {
            //            renderer: $.fn.dataTable.Responsive.renderer.tableAll()
            //        }
            //    },");
            if (strlen($this->initialSearch) > 0) {
                $js->appendln("'oSearch': {'sSearch': '" . $this->initialSearch . "'},")->br();
            }

            if ($this->fixedHeader) {
                $js->appendln('fixedHeader: true,')->br();
            }
            if ($this->scrollY) {
                $scrollY = $this->scrollY;
                if (is_bool($scrollY)) {
                    $scrollY = 'true';
                }
                $js->appendln('scrollY :        ' . $scrollY . ',')->br();
            }
            if ($this->colReorder) {
                $js->appendln('colReorder : true,')->br();
            }

            if ($this->fixedColumn) {
                $scrollY = $this->scrollY;
                if (is_bool($scrollY) || !is_numeric($scrollY)) {
                    $scrollY = '300';
                }

                $js->appendln('scrollY : ' . $scrollY . ',')->br()
                    ->appendln('scrollX : true,')->br()
                    ->appendln('scrollCollapse : true,')->br();
                $leftColumns = $this->fixedColumn;
                if ($this->checkbox) {
                    $leftColumns += 1;
                }
                $js->appendln('fixedColumns: {
                    leftColumns: ' . $leftColumns . ',
                    left: ' . $leftColumns . ',
                },')->br();
            }
            //data table options
            $js->appendln($this->options->toJsonRow('paging'))->br()
                ->appendln($this->options->toJsonRow('pagingType'))->br()
                ->appendln($this->options->toJsonRow('lengthChange'))->br()
                ->appendln($this->options->toJsonRow('searching'))->br()
                ->appendln($this->options->toJsonRow('info'))->br()
                ->appendln($this->options->toJsonRow('deferRender'))->br()
                ->appendln($this->options->toJsonRow('autoWidth'))->br()
                ->appendln($this->options->toJsonRow('ordering'))->br()
                ->appendln($this->options->toJsonRow('stateSave'))->br()
                ->appendln($this->options->toJsonRow('scrollY'))->br()
                ->appendln($this->fixedColumn ? '' : $this->options->toJsonRow('scrollX'))->br()
                ->br();
            if ($this->ajax) {
                $this->options->setOption('serverSide', true);
                $js->append('')
                    ->appendln('bRetrieve: true,')->br()
                    ->appendln($this->options->toJsonRow('processing'))->br()
                    ->appendln($this->options->toJsonRow('serverSide'))->br()
                    ->appendln("sAjaxSource: '" . $ajaxUrl . "',")->br()
                    ->appendln("sServerMethod: '" . strtoupper($this->ajax_method) . "',")->br()
                    ->appendln("fnServerData: function ( sSource, aoData, fnCallback, oSettings ) {
        var data_quick_search = [];
        jQuery('#" . $this->id() . " .data_table-quick_search').each(function(){
            if (jQuery(this).val() != '') {
                var input_name = jQuery(this).attr('name');
                var cur_transforms = jQuery(this).attr('transforms');
                data_quick_search.push({'name': input_name, 'value': jQuery(this).val(), 'transforms': cur_transforms});
            }
        });
        aoData.push({'name': 'dttable_quick_search', 'value': JSON.stringify(data_quick_search)});
        oSettings.jqXHR = $.ajax( {
            'dataType': 'json',
            'type': '" . strtoupper($this->ajax_method) . "',
            'url': sSource,
            'data': aoData,
            'success': function(data) {
                fnCallback(data.datatable);
                if(data.js && data.js.length>0) {
                    var script = data.js;
                    script = cresenity.base64.decode(script);
                    if(script.trim().length > 0) {
                        eval(script);
                    }
                }
                jQuery('." . $this->id . "-check-all').removeAttr('checked');
                jQuery('." . $this->id . "-check-all').prop('checked',false);
            },
            'error': function(a,b,c) {
                if(window.cresenity) {
                    window.cresenity.message(a);
                } else {
                    $.cresenity.message(a);
                }
            }
        })
    },")
                    ->br()
                    ->appendln("fnRowCallback: function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
        var footer_action = $('#" . $this->id . "_wrapper .footer_action');
        " . ($this->haveFooterAction() ? 'footer_action.html(' . json_encode($this->footerActionList->html()) . ');' : '') . '
        ' . ($this->haveFooterAction() ? '' . $this->footerActionList->js() . '' : '') . "

        footer_action.css('position','absolute').css('left','275px').css('margin','4px 8px 2px 10px');
        for(var i=0;i<$(nRow).find('td').length;i++) {
            //get head data align
            var dataAlign = $('#" . $this->id . "').find('thead th:eq('+i+')').data('align');
            var dataAction = $('#" . $this->id . "').find('thead th:eq('+i+')').data('action');
            var dataNoLineBreak = $('#" . $this->id . "').find('thead th:eq('+i+')').data('no-line-break');
            var dataClass = $('#" . $this->id . "').find('thead th:eq('+i+')').data('class');
            if(dataAction) {
                $('td:eq('+i+')', nRow).addClass(dataAction);
            }
            if(dataAlign) {
                $('td:eq('+i+')', nRow).addClass(dataAlign);
            }
            if(dataNoLineBreak) {
                $('td:eq('+i+')', nRow).addClass(dataNoLineBreak);
            }
            if(dataClass) {
                $('td:eq('+i+')', nRow).addClass(dataClass);
            }
        }
    },")->br()->appendln('fnInitComplete: function() {
        try {
            this.fnAdjustColumnSizing(true);
        } catch (e) {}
    },')->br();
            }

            $jqueryui = 'bJQueryUI: false,';

            if (c::manager()->asset()->module()->isRegisteredModule('jquery.ui') || c::manager()->asset()->module()->isRegisteredModule('jquery-ui-1.12.1.custom')) {
                $jqueryui = 'bJQueryUI: true,';
            }
            $js->appendln('buttons:        ' . json_encode($this->buttons) . ',')->br();

            /*
              $js->append("
              initComplete : function() {
              var input = $('#" . $this->id() . " .dataTables_filter input').unbind();
              var self = this.api();
              var searchButton = $('<button>')
              .text('search')
              .click(function() {
              self.search(input.val()).draw();
              });
              var clearButton = $('<button>')
              .text('clear')
              .click(function() {
              input.val('');
              searchButton.click();
              });

              $('#" . $this->id() . " .dataTables_filter').append(searchButton, clearButton);
              },
              ");
             *
             */
            $js->appendln($jqueryui)->br()
                ->appendln('iDisplayLength: ' . $this->displayLength . ',')->br()
                ->appendln('bSortCellsTop: ' . $hsVal . ',')->br()
                ->appendln('aaSorting: [],')->br()

                ->appendln('oLanguage: ' . json_encode($this->getLegacyLabels()) . ',')->br()
                ->appendln('language: ' . json_encode($this->getLabels()) . ',')->br()
                ->appendln('aoColumns: vaoColumns,')->br()
                ->appendln('aLengthMenu: [
                    [' . $km . '],
                    [' . $vm . ']
				],')->br();

            if ($this->dom == null) {
                $this->dom = '<""l>t<"F"<".footer_action">frp>';
            }
            $dom = str_replace("'", "\'", $this->dom);
            $js->append('')
                ->appendln("sDom: '" . $dom . "',")->br();

            $js->append('')
                ->decIndent()->appendln('});')->br();

            $js->appendln('function buildFilters_' . $this->id . '() {')->br()
                ->incIndent()
                ->appendln("var quick_search = jQuery('<tr>');")->br()
                ->appendln("
    jQuery('#" . $this->id . " thead th').each( function (i) {
        var title = jQuery('#" . $this->id . " thead th').eq( jQuery(this).index() ).text();
        var haveAction = " . ($this->haveRowAction() ? '1' : '0') . ';

        var placeholder =  ' . ($this->haveQuickSearchPlaceholder ? $quickSearchPlaceholder : "''") . ";
        var totalTh = jQuery('#" . $this->id . " thead th').length;
        var input = '';
        var haveCheckbox = " . ($this->checkbox ? '1' : '0') . ';
        var haveNumbering = ' . ($this->numbering ? '1' : '0') . ';
        var columnLeftOffset = ' . $this->getColumnLeftOffset() . ';
        var columnRightOffset = ' . $this->getColumnRightOffset() . ';
        var currentIndex = jQuery(this).index();

        if(currentIndex>=columnLeftOffset && currentIndex<= (totalTh-columnRightOffset)) {
            var i2 = -columnLeftOffset;


            var allColumn = ' . json_encode($this->columns) . ";
            var column = allColumn[jQuery(this).index()+i2];
            var transforms = {};
            if(column) {
                if(hasOwnProperty.call(column, 'transforms')) {
                    transforms = JSON.stringify(column.transforms);
                }

                if(column.searchable) {
                    var searchType = column.searchType || 'text';
                    if(searchType=='text') {
                        input = jQuery('<input>');
                        input.attr('type', 'text');
                        input.attr('name', 'dt_table_qs-' + jQuery(this).attr('field_name'));
                        input.attr('class', 'data_table-quick_search');

                        input.attr('transforms', transforms);
                        input.attr('placeholder', placeholder );
                    }
                    if(searchType=='select') {
                        input = jQuery('<select>');
                        input.attr('name', 'dt_table_qs-' + jQuery(this).attr('field_name'));
                        input.attr('class', 'data_table-quick_search');

                        input.attr('transforms', transforms);
                        input.attr('placeholder', 'Search ' + title );

                        var options = column.searchOptions||[];
                        for(let optionKey in options) {
                            var optionElement = jQuery('<option>');
                            optionElement.attr('value',optionKey);
                            optionElement.append(options[optionKey]);
                            input.append(optionElement);
                        }
                    }
                }
            }

        }
        if(input) {
            input.attr('data-column-index',i);
        }
        var td = jQuery('<td>').append(input);
        quick_search.append(td);
    });")->br()
                ->appendln($varName . ".children('thead').append(quick_search);")->br()
                ->decIndent()
                ->appendln('}')->br()
                ->appendln('var dttable_quick_search = ' . ($this->quickSearch ? '1' : '0') . ';')->br()
                ->appendln('if (dttable_quick_search == "1") { buildFilters_' . $this->id . '(); }');
            if ($this->customSearchSelector != null) {
                $js->appendln("
                    $('" . $this->customSearchSelector . "').keyup(() => {
                        " . $varNameOTable . ".fnFilter($('" . $this->customSearchSelector . "').val());
                    });
                ");
            }
            $js->appendln("
                jQuery('#" . $this->id . " .data_table-quick_search').on('input', function() {
                    var inputType = $(this).prop('tagName');
                    " . ($this->ajax
                            ? $varName . '.fnClearTable( 0 );' . $varName . '.fnDraw();'
                            : "if (inputType.toLowerCase() == 'select' && $(this).val()) {
                                " . $varName . ".fnFilter(\"^\"+$(this).val()+\"$\",$(this).attr('data-column-index'), true)
                            } else {
                                " . $varName . ".fnFilter($(this).val(),$(this).attr('data-column-index'))
                            };") . '
                });
            ');
        }
        if ($this->checkbox) {
            $js->appendln("
                jQuery('." . $this->id . "-check-all').click(function() {
                    if(jQuery(this).is(':checked')) {
                        jQuery('.checkbox-" . $this->id . "').attr('checked','checked');
                        jQuery('.checkbox-" . $this->id . "').prop('checked',true);
                    } else {
                        jQuery('.checkbox-" . $this->id . "').removeAttr('checked');
                        jQuery('.checkbox-" . $this->id . "').prop('checked',false);
                    }
                });
            ");
        }

        $js->appendln($this->js_cell);
        if (!$this->ajax) {
            $js->append(parent::js($indent))->br();
            if (is_array($this->data)) {
                foreach ($this->data as $row) {
                    if ($row == null) {
                        continue;
                    }
                    if ($row instanceof CRenderable) {
                        $js->appendln($row->js())->br();

                        continue;
                    }
                    foreach ($row as $rowV) {
                        if ($rowV instanceof CRenderable) {
                            $js->appendln($rowV->js())->br();
                        }
                    }
                }
            }
        }

        if ($this->footer) {
            foreach ($this->footerFields as $footerField) {
                $fval = $footerField->getValue();
                if ($fval instanceof CRenderable) {
                    $js->appendln($fval->js())->br();
                }
            }
        }

        if ($this->haveDataTableViewAction) {
            $js->append("
                $('#" . $this->id() . '-widget-box [name="' . $this->id() . "-data-table-view\"]').on('change', function() {
                    $('#" . $this->id() . "-widget-box > .widget-content')
                        .removeClass('data-table-col-view data-table-row-view')
                        .addClass(this.value);
                });
            ");
        }

        if ($this->haveRowSelection()) {
            if ($this->applyDataTable > 0) {
                $js->append("
                $('#" . $this->id . " tbody').on( 'click', 'tr', function () {
                    if ($(this).hasClass('selected') ) {
                        $(this).removeClass('selected');
                    }
                    else {
                        table.$('tr.selected').removeClass('selected');
                        $(this).addClass('selected');
                    }
                });


                ");
            }
        }

        //domElements
        if (is_array($this->domElements)) {
            foreach ($this->domElements as $classElement => $domElement) {
                if (c::isCallable($domElement)) {
                    $domElement = c::toCallable($domElement);
                    $domElement = $domElement();
                }
                $js->appendln("
                    jQuery('#" . $this->id . '_wrapper .' . $classElement . "').html(`" . addslashes($domElement->html()) . '`)
                ');

                $js->appendln($domElement->js());
            }
        }
        if ($this->applyDataTable) {
            $js->appendln("$('#" . $this->id . "').data('cappDataTable'," . $varNameOTable . ');');
            if ($this->autoRefreshInterval) {
                $js->appendln("$('#" . $this->id . "').data('cappDataTableAutoRefreshHandler', setInterval( function () {
                    $('#" . $this->id . "').DataTable().ajax.reload(null, false);
                }, " . ((int) $this->autoRefreshInterval) . ' * 1000));');
            }
        }

        return $js->text();
    }
}
