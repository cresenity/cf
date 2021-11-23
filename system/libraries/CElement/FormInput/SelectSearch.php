<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 15, 2018, 12:00:39 AM
 */
class CElement_FormInput_SelectSearch extends CElement_FormInput {
    use CTrait_Compat_Element_FormInput_SelectSearch;

    protected $query;

    protected $formatSelection;

    protected $formatResult;

    protected $keyField;

    protected $searchField;

    protected $multiple;

    protected $placeholder;

    protected $autoSelect;

    protected $minInputLength;

    protected $dropdownClasses;

    protected $delay;

    protected $valueCallback;

    protected $requires;

    public function __construct($id) {
        parent::__construct($id);

        $this->dropdownClasses = [];
        $this->type = 'selectsearch';
        $this->query = '';
        $this->formatSelection = '';
        $this->formatResult = '';
        $this->keyField = '';
        $this->searchField = '';
        $this->placeholder = 'Search for a item';
        $this->multiple = false;
        $this->autoSelect = false;
        $this->minInputLength = 0;
        $this->delay = 100;
        $this->requires = [];
        $this->valueCallback = null;
    }

    public static function factory($id) {
        return new CElement_FormInput_SelectSearch($id);
    }

    public function setValueCallback(callable $callback, $require = '') {
        $this->valueCallback = $callback;
        if (strlen($require) > 0) {
            $this->requires[] = $require;
        }

        return $this;
    }

    public function setMultiple($bool = true) {
        $this->multiple = $bool;

        return $this;
    }

    public function setDelay($val) {
        $this->delay = $val;

        return $this;
    }

    public function setAutoSelect($bool = true) {
        $this->autoSelect = $bool;

        return $this;
    }

    public function setMinInputLength($minInputLength) {
        $this->minInputLength = $minInputLength;

        return $this;
    }

    public function setKeyField($keyField) {
        $this->keyField = $keyField;

        return $this;
    }

    public function setSearchField($searchField) {
        $this->searchField = $searchField;

        return $this;
    }

    public function setQuery($query) {
        $this->query = $query;

        return $this;
    }

    public function setFormatResult($fmt) {
        $this->formatResult = $fmt;

        return $this;
    }

    public function setFormatSelection($fmt) {
        $this->formatSelection = $fmt;

        return $this;
    }

    public function setPlaceholder($placeholder) {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function addDropdownClass($c) {
        if (is_array($c)) {
            $this->dropdownClasses = array_merge($c, $this->dropdownClasses);
        } else {
            $this->dropdownClasses[] = $c;
        }

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $custom_css = $this->custom_css;

        $custom_css = $this->renderStyle($custom_css);
        $disabled = '';
        if ($this->disabled) {
            $disabled = ' disabled="disabled"';
        }
        $multiple = '';
        if ($this->multiple) {
            $multiple = ' multiple="multiple"';
        }
        if (strlen($custom_css) > 0) {
            $custom_css = ' style="' . $custom_css . '"';
        }

        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }

        $classes = $classes . ' form-control ';

        $html->setIndent($indent);
        $value = null;
        if ($this->autoSelect) {
            $db = CDatabase::instance();
            $rjson = 'false';

            $q = 'select `' . $this->keyField . '` from (' . $this->query . ') as a limit 1';
            $value = cdbutils::get_value($q);
        }
        if (strlen($this->value) > 0) {
            $value = $this->value;
        }
        $additionAttribute = '';
        foreach ($this->attr as $k => $v) {
            $additionAttribute .= ' ' . $k . '="' . $v . '"';
        }
        $html->appendln('<select class="' . $classes . '" name="' . $this->name . '" id="' . $this->id . '" ' . $disabled . $custom_css . $multiple . $additionAttribute . '">');

        // select2 4.0 using option to set default value
        if (strlen($this->value) > 0 || $this->autoSelect) {
            $db = CDatabase::instance();
            $rjson = 'false';

            if ($this->autoSelect) {
                $q = 'select * from (' . $this->query . ') as a limit 1';
            } else {
                $q = 'select * from (' . $this->query . ') as a where `' . $this->keyField . '`=' . $db->escape($this->value);
            }
            $r = $db->query($q)->resultArray(false);
            if (count($r) > 0) {
                $row = $r[0];
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (isset($this->valueCallback) && is_callable($this->valueCallback)) {
                    foreach ($row as $k => $v) {
                        $row[$k] = $this->valueCallback($row, $k, $v);
                    }
                }
                $strSelection = $this->formatSelection;
                $strSelection = str_replace("'", "\'", $strSelection);
                preg_match_all("/{([\w]*)}/", $strSelection, $matches, PREG_SET_ORDER);

                foreach ($matches as $val) {
                    $str = $val[1]; //matches str without bracket {}
                    $b_str = $val[0]; //matches str with bracket {}

                    $strSelection = str_replace($b_str, carr::get($row, $str), $strSelection);
                }

                $html->appendln('<option value="' . $this->value . '">' . $strSelection . '</option>');
            }
        }
        $html->appendln('</select>');
        $html->br();

        return $html->text();
    }

    public function createAjaxUrl() {
        $ajaxMethod = CAjax::createMethod();
        $ajaxMethod->setType('SearchSelect');
        $ajaxMethod->setData('query', $this->query);
        $ajaxMethod->setData('keyField', $this->keyField);
        $ajaxMethod->setData('searchField', $this->searchField);
        $ajaxMethod->setData('valueCallback', $this->valueCallback);

        $ajaxUrl = $ajaxMethod->makeUrl();

        return $ajaxUrl;
    }

    private function generateSelect2Template($template) {
        //escape the character
        $template = str_replace("'", "\'", $template);
        preg_match_all("/{([\w]*)}/", $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $val) {
            $str = carr::get($val, 1); //matches str without bracket {}
            $bracketStr = carr::get($val, 0); //matches str with bracket {}
            if (strlen($str) > 0) {
                $template = str_replace($bracketStr, "'+item." . $str . "+'", $template);
            }
        }

        return $template;
    }

    public function js($indent = 0) {
        $ajaxUrl = $this->createAjaxUrl();

        $strSelection = $this->formatSelection;
        $strResult = $this->formatResult;

        $strSelection = $this->generateSelect2Template($strSelection);
        $strResult = $this->generateSelect2Template($strResult);

        if (strlen($strResult) == 0) {
            $searchFieldText = c::value($this->searchField);
            if (strlen($searchFieldText) > 0) {
                $strResult = "'+item." . $searchFieldText . "+'";
            }
        }
        if (strlen($strSelection) == 0) {
            $searchFieldText = c::value($this->searchField);
            if (strlen($searchFieldText) > 0) {
                $strSelection = "'+item." . $searchFieldText . "+'";
            }
        }

        $strResult = preg_replace("/[\r\n]+/", '', $strResult);
        $placeholder = 'Search for a item';
        if (strlen($this->placeholder) > 0) {
            $placeholder = $this->placeholder;
        }
        $strJsChange = '';
        if ($this->submit_onchange) {
            $strJsChange = "$(this).closest('form').submit();";
        }

        $strJsInit = '';
        if ($this->autoSelect) {
            $db = CDatabase::instance();
            $rjson = 'false';

            $q = 'select * from (' . $this->query . ') as a limit 1';
            $r = $db->query($q)->resultArray(false);
            if (count($r) > 0) {
                $r = $r[0];
                if ($this->valueCallback != null && is_callable($this->valueCallback)) {
                    foreach ($r as $k => $val) {
                        $r[$k] = $this->valueCallback($r, $k, $val);
                    }
                }
            }
            $rjson = json_encode($r);

            $strJsInit = '
                initSelection : function (element, callback) {
                    var data = ' . $rjson . ';
                    callback(data);
                },
            ';
        }

        $strMultiple = '';
        if ($this->multiple) {
            $strMultiple = " multiple:'true',";
        }
        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }

        //$classes = $classes . " form-control ";

        $dropdownClasses = $this->dropdownClasses;
        $dropdownClasses = implode(' ', $dropdownClasses);
        if (strlen($dropdownClasses) > 0) {
            $dropdownClasses = ' ' . $dropdownClasses;
        }

        $str = "

            $('#" . $this->id . "').select2({
                width: '100%',
                placeholder: '" . $placeholder . "',
                minimumInputLength: '" . $this->minInputLength . "',
                ajax: { // instead of writing the function to execute the request we use Select2's convenient helper
                        url: '" . $ajaxUrl . "',
                        dataType: 'jsonp',
                        quietMillis: " . $this->delay . ',
                        delay: ' . $this->delay . ',
                        ' . $strMultiple . '
                        data: function (params) {
                            return {
                                q: params.term, // search term
                                page: params.page,
                                limit: 10
                            };
                        },
                        processResults: function (data, params) {
                            // parse the results into the format expected by Select2
                            // since we are using custom formatting functions we do not need to
                            // alter the remote JSON data, except to indicate that infinite
                            // scrolling can be used
                            params.page = params.page || 1;
                            var more = (params.page * 10) < data.total;
                            return {
                                results: data.data,
                                pagination: {
                                    more: more
                                }
                            };
                        },
                        cache:true,
                        error: function (jqXHR, status, error) {
                            if(cresenity && cresenity.handleAjaxError) {
                                cresenity.handleAjaxError(jqXHR, status, error);
                            }
                        }
                    },
                ' . $strJsInit . "
                templateResult: function(item) {
                    if (typeof item.loading !== 'undefined') {
                        return item.text;
                    }
                    return $('<div>" . $strResult . "</div>');
                }, // omitted for brevity, see the source of this page
                templateSelection: function(item) {
                    if (item.id === '' || item.selected) {
                        return item.text;
                    }
                    else {
                        return $('<div>" . $strSelection . "</div>');
                    }
                },  // omitted for brevity, see the source of this page
                dropdownCssClass: '" . $dropdownClasses . "', // apply css that makes the dropdown taller
                containerCssClass : 'tpx-select2-container " . $classes . "'
            }).change(function() {
                " . $strJsChange . "
            });
            $('#" . $this->id . "').on('select2:open',function(event){
                var modal = $('#" . $this->id . "').closest('.modal');
                if(modal[0]){
                    var modalZ=modal.css('z-index');
                    var newZ=parseInt(modalZ)+1;
                    $('#" . $this->id . "').data('select2').\$container.css('z-index',newZ);
                    $('#" . $this->id . "').data('select2').\$dropdown.css('z-index',newZ);
                    $('#" . $this->id . "').data('select2').\$element.css('z-index',newZ);
                    $('#" . $this->id . "').data('select2').\$results.css('z-index',newZ);
                    $('#" . $this->id . "').data('select2').\$selection.css('z-index',newZ);
                }
            });
        ";
        if ($this->valueCallback != null && is_callable($this->valueCallback)) {
            $str .= "
                $('#" . $this->id . "').trigger('change');
            ";
        }

        $js = new CStringBuilder();
        $js->append(parent::jsChild($indent))->br();
        $js->setIndent($indent);
        //echo $str;
        $js->append($str)->br();

        return $js->text();
    }
}
