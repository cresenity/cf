<?php

use BaconQrCode\Renderer\RendererStyle\RendererStyle;

/**
 * @deprecated since 1.2
 */
//@codingStandardsIgnoreStart
class CForm extends CElement_Element {
    protected $name;

    protected $method;

    protected $autocomplete;

    protected $layout;

    protected $action;

    protected $target;

    protected $enctype;

    protected $validation;

    protected $ajax_submit;

    protected $ajax_success_script_callback;

    protected $ajax_datatype;

    protected $ajax_redirect;

    protected $ajax_process_progress;

    protected $ajax_process_progress_cancel;

    protected $ajax_upload_progress;

    protected $ajax_redirect_url;

    protected $ajax_process_id;

    protected $ajax_submit_handlers;

    protected $ajax_submit_target;

    protected $ajax_submit_target_class;

    protected $auto_set_focus;

    protected $action_before_submit;

    protected $disable_js;

    public function __construct($form_id = '') {
        parent::__construct($form_id);
        $this->tag = 'form';

        $this->name = $this->id;
        $this->method = 'POST';
        $this->target = '_self';
        $this->layout = 'horizontal';
        $this->action = '';
        $this->autocomplete = true;
        $this->enctype = 'application/x-www-form-urlencoded';
        $this->validation = true;
        $this->ajax_submit = false;
        $this->ajax_success_script_callback = '';
        $this->ajax_datatype = 'text';
        $this->ajax_upload_progress = false;
        $this->ajax_process_progress = false;
        $this->ajax_process_progress_cancel = false;
        $this->ajax_process_id = cutils::randmd5();
        $this->ajax_redirect = true;
        $this->ajax_redirect_url = '';
        $this->ajax_submit_handlers = [];
        $this->ajax_submit_target = false;
        $this->ajax_submit_target_class = false;
        $this->auto_set_focus = true;
        $this->action_before_submit = '';
        $this->disable_js = false;

        CManager::instance()->registerModule('validation');
    }

    public static function factory($id = '') {
        return new CForm($id);
    }

    public function set_name($name) {
        $this->name = $name;

        return $this;
    }

    public function set_layout($layout) {
        $this->layout = $layout;

        return $this;
    }

    public function set_action($action) {
        $this->action = $action;

        return $this;
    }

    /**
     * @param string $method
     *
     * @return CForm
     */
    public function set_method($method) {
        $this->method = $method;

        return $this;
    }

    public function set_target($target) {
        $this->target = $target;

        return $this;
    }

    public function set_enctype($enctype) {
        $this->enctype = $enctype;

        return $this;
    }

    public function set_autocomplete($bool) {
        $this->autocomplete = $bool;

        return $this;
    }

    public function set_validation($bool) {
        $this->validation = $bool;

        return $this;
    }

    public function set_ajax_submit($bool) {
        $this->ajax_submit = $bool;

        return $this;
    }

    public function set_ajax_datatype($datatype) {
        $this->ajax_datatype = $datatype;

        return $this;
    }

    public function set_ajax_submit_target($target) {
        $this->ajax_submit_target = $target;

        return $this;
    }

    public function set_disable_js($bool) {
        $this->disable_js = $bool;

        return $this;
    }

    public function set_ajax_submit_target_class($target) {
        $this->ajax_submit_target_class = $target;

        return $this;
    }

    public function set_ajax_success_script_callback($jsfunc) {
        $this->ajax_success_script_callback = $jsfunc;

        return $this;
    }

    public function set_ajax_redirect($bool) {
        $this->ajax_redirect = $bool;

        return $this;
    }

    public function set_ajax_upload_progress($bool) {
        $this->ajax_upload_progress = $bool;

        return $this;
    }

    public function set_ajax_process_progress($bool) {
        $this->ajax_process_progress = $bool;

        return $this;
    }

    public function set_ajax_process_progress_cancel($bool) {
        $this->ajax_process_progress_cancel = $bool;

        return $this;
    }

    public function set_ajax_redirect_url($url) {
        $this->ajax_redirect_url = $url;

        return $this;
    }

    public function set_action_before_submit($action_before_submit) {
        $this->action_before_submit = $action_before_submit;

        return $this;
    }

    public function set_auto_set_focus($bol) {
        $this->auto_set_focus = $bol;

        return $this;
    }

    /**
     * @param string $handler_name
     *
     * @return CHandler
     */
    public function add_ajax_submit_handler($handler_name) {
        $handler = CHandler::factory($this->id, 'submit', $handler_name);
        $this->ajax_submit_handlers[] = $handler;

        return $handler;
    }

    public function toarray($indent = 0) {
        $data = [];
        $data = array_merge_recursive($data, parent::toarray());

        if (strlen($this->action) > 0) {
            $data['attr']['action'] = $this->action;
        }
        if (strlen($this->method) > 0) {
            $data['attr']['method'] = $this->method;
        }

        return $data;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->setIndent($indent);

        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }
        $custom_css = $this->custom_css;
        $custom_css = $this->renderStyle($custom_css);
        if (strlen($custom_css) > 0) {
            $custom_css = ' style="' . $custom_css . '"';
        }
        $addition_str = '';
        if ($this->autocomplete) {
            $addition_str .= ' autocomplete="on"';
        } else {
            $addition_str .= ' autocomplete="off"';
        }
        if (strlen($this->enctype) > 0) {
            $addition_str .= ' enctype="' . $this->enctype . '"';
        }

        $form_style_layout = '';
        if (strlen($this->layout) > 0) {
            $form_style_layout = 'form-' . $this->layout;
        }
        $html->appendln('<form id="' . $this->id . '" class="' . $form_style_layout . ' ' . $classes . '" name="' . $this->name . '" target="' . $this->target . '" action="' . $this->action . '" method="' . $this->method . '"' . $addition_str . ' ' . $custom_css . '>')
            ->incIndent()
            ->br();

        if ($this->ajax_process_progress) {
            $html->appendln('<input type="hidden" id="cprocess_id" name="cprocess_id" value="' . $this->ajax_process_id . '">');
        }
        $html->appendln($this->htmlChild($html->getIndent()));

        $html->decIndent()
            ->appendln('</form>');

        return $html->text();
    }

    public function js($indent = 0) {
        if ($this->disable_js) {
            return parent::js($indent);
        }
        $js = new CStringBuilder();
        $js->setIndent($indent);
        if ($this->ajax_submit) {
            $ajax_url = '';
            $ajax_process_script = '';
            $ajax_process_done_script = '';
            if ($this->ajax_process_progress) {
                $ajax_process_url = CAjaxMethod::factory()->set_type('form_process')
                    ->set_data('form', serialize($this))->set_method('POST')
                    ->makeurl();
                $ajax_process_script_buttons = '	buttons: {}, ';
                if ($this->ajax_process_progress_cancel) {
                    $ajax_process_script_buttons = "
                        'buttons': {
                                'Cancel': {
                                        'primary': true,
                                        'click': function() {
                                                jQuery.ajax('" . $ajax_process_url . "', {
                                                        dataType: 'json',
                                                        type: 'POST',
                                                        data : {ajax_process_id:'" . $this->ajax_process_id . "',cancel:true},
                                                        success: function(data) {
                                                                $(this).dialog2('close');
                                                        },
                                                        error: function(xhr, status, error) {
                                                                $.cresenity.message('error','[AJAX PROCESS] ' + status + ' - Server reponse is: ' + xhr.responseText);
                                                        }
                                                });

                                        }
                                }
                        },
                    ";
                }
                $ajax_process_script = '
                    if(cprocess_run_once_' . $this->id . '==false) {
                           cprocess_run_once' . $this->id . '=true;
                           ctimer_' . $this->id . " = setInterval(function()  {

                                   jQuery.ajax('" . $ajax_process_url . "', {
                                           dataType: 'json',
                                           type: 'POST',
                                           data : {ajax_process_id:'" . $this->ajax_process_id . "'},
                                           success: function(data) {
                                                   var percentComplete = data.percent;
                                                   var info = data.info;
                                                   if(info=='undefined') info = '';
                                                   //update the progress bar
                                                   //do create progress
                                                   var progress = $('#progress_" . $this->id . "');
                                                   if(progress.length==0) {
                                                           progress = $('<div id=\"progress_" . $this->id . '"class="progress progress-striped active"><div id="bar_' . $this->id . "\" class=\"bar\" style=\"width: 0%;\"><p>0%</p></div></div>');
                                                           var span = $('<div class=\"span12\">');
                                                           var span_info = $('<div class=\"span12\" id=\"info_" . $this->id . "\">');
                                                           var div = $('<div class=\"row-fluid\" style=\"width:auto\">');
                                                           div.append(span);
                                                           div.append(span_info);
                                                           span.append(progress);
                                                           $.cresenity.dialog.show(div,{
                                                                   " . $ajax_process_script_buttons . "
                                                                   showCloseHandle: false,
                                                                   closeOnEscape: false,
                                                                   closeOnOverlayClick: false,
                                                                   title: 'Progress',
                                                                   autoOpen: true,
                                                                   removeOnClose: true
                                                           });

                                                   };
                                                   var percentVal = percentComplete + '%';
                                                   $('#bar_" . $this->id . "').width(percentVal);
                                                   $('#bar_" . $this->id . "').find('p').html(percentVal);
                                                   $('#info_" . $this->id . "').html(info);
                                                   if(percentComplete==100) {
                                                           $('#bar_" . $this->id . "').parent().parent().parent().remove();
                                                   }
                                           },
                                           error: function(xhr, status, error) {
                                                   $.cresenity.message('error','[AJAX PROCESS] ' + status + ' - Server reponse is: ' + xhr.responseText);
                                           }
                                   });
                           },2000);
                   }
                ";
                $ajax_process_done_script = '
                    clearInterval(ctimer_' . $this->id . ");
                    var progress = $('#progress_" . $this->id . "');
                    if(progress.length>0) {
                        modal = progress.closest('.modal');
                        if(modal.length>0) {
                            modal.remove();
                            $('.modal-backdrop').remove();
                        }
                    }
                ";
            }
            $redirect_url = $this->ajax_redirect_url;
            $ajax_url = $this->action;
            if (strlen($redirect_url) == 0) {
                //ajax to this page
                $ajax_url = curl::base() . crouter::complete_uri();
            }
            if (strlen($redirect_url) == 0) {
                //redirect to this page
                $redirect_url = curl::base() . crouter::complete_uri();
            }
            $script_redirect_url = '';
            if ($this->ajax_redirect) {
                $script_redirect_url = "document.location.href = '" . $redirect_url . "';";
            }
            $script_callback = '';
            if ($this->ajax_redirect) {
            }
            if (count($this->ajax_submit_handlers) > 0) {
                $script_redirect_url = '';
                foreach ($this->ajax_submit_handlers as $handler) {
                    $script_redirect_url .= $handler->js();
                }
            }
            $upload_progress_before_submit = '';
            $upload_progress_success = '';
            $upload_progress_upload = '';
            if ($this->ajax_upload_progress) {
                $upload_progress_before_submit = "
					var progress = $('#" . $this->id . "').find('#progress_" . $this->id . "');
					if(progress.length==0) {
						//find progress first
						var progress = $('#progress_" . $this->id . "');
						if(progress.length==0) {
							//do create progress
							progress = $('<div id=\"progress_" . $this->id . '"class="progress progress-striped active"><div id="bar_' . $this->id . "\" class=\"bar\" style=\"width: 0%;\"><p>0%</p></div></div>');
							var span = $('<div class=\"span12\">');
							var span_info = $('<div class=\"span12\" id=\"info_" . $this->id . "\">');
							var div = $('<div class=\"row-fluid\" style=\"width:auto\">');
							div.append(span);
							div.append(span_info);
							span.append(progress);
							$.cresenity.dialog.show(div,{
								showCloseHandle: false,
								closeOnEscape: false,
								closeOnOverlayClick: false,
								title: 'Uploading',
								autoOpen: true,
								buttons: {},
								removeOnClose: true
							});

						};



					} else {
						var percentVal = '0%';
						$('#bar_" . $this->id . "').width(percentVal);
						$('#bar_" . $this->id . "').find('p').html(percentVal);
						$('#info_" . $this->id . "').html('&nbsp;');
					}
				";
                $upload_progress_upload = "
					var percentVal = percentComplete + '%';
					$('#bar_" . $this->id . "').width(percentVal);
					$('#bar_" . $this->id . "').find('p').html(percentVal);
					$('#info_" . $this->id . "').html('Uploading '+position+'/'+total+'<br/>'+percentVal+' Completed');
					if(percentComplete==100) {
						$('#bar_" . $this->id . "').parent().parent().parent().remove();
						" . $ajax_process_script . '
					}
				';
                $upload_progress_success = "
					var percentVal = '100%';
					$('#bar_" . $this->id . "').width(percentVal);
					$('#bar_" . $this->id . "').find('p').html(percentVal);
					$('#info_" . $this->id . "').html('&nbsp;');
				";
            }

            $js->appendln('
				var cprocess_run_once_' . $this->id . ' = false;
				var ctimer_' . $this->id . ' = false;
			');
            $ajax_process_without_upload = $ajax_process_script;
            if ($this->ajax_upload_progress) {
                $ajax_process_without_upload = '';
            }

            $on_success_script = "
				$('#" . $this->id . "').removeClass('loading');
				$('#" . $this->id . "').find('*').removeClass('disabled');


				if(typeof data=='object') {
					var result = data.result;
					var message = data.message;
					if(result=='OK'||result=='1'||result===true) {
						$.cresenity.message('success',message);
						" . $script_redirect_url . "
					} else {
						$.cresenity.message('error',message);
					}
				} else if(typeof data== 'string') {
					if(data.toLowerCase().substring(0,5) != 'error') {
						if(data!='') {
							$.cresenity.message('success',data);
						} else {
							//do nothing
							//the message must be set on session
						}
						" . $script_redirect_url . "
					} else {
						$.cresenity.message('error',data);
					}


				} else {
					$.cresenity.message('success',data);
					" . $script_redirect_url . '
				}
			';
            $on_before_submit = '';
            if (strlen($this->ajax_submit_target) > 0) {
                $on_before_submit = "
                                jQuery('#" . $this->ajax_submit_target . "').children().hide();
                                jQuery('#" . $this->ajax_submit_target . "').append(jQuery('<div>').attr('id','#" . $this->ajax_submit_target . "-loading').css('text-align','center').css('margin-top','100px').css('margin-bottom','100px').append(jQuery('<i>').addClass('icon icon-repeat icon-spin icon-4x')))
                            ";

                $this->ajax_datatype = 'json';
                //the response is json
                $on_success_script = "
				jQuery('#" . $this->ajax_submit_target . "').html(data.html);
				var script = $.cresenity.base64.decode(data.js);

				eval(script);
				jQuery('#" . $this->ajax_submit_target . "').removeClass('loading');
				jQuery('#" . $this->ajax_submit_target . "').data('xhr',false);
				if(jQuery('#" . $this->ajax_submit_target . "').find('.prettyprint').length>0) {
					window.prettyPrint && prettyPrint();
				}
				";
            }

            if (strlen($this->ajax_submit_target_class) > 0) {
                $on_before_submit = "
					jQuery('." . $this->ajax_submit_target_class . "').children().hide();
					jQuery('." . $this->ajax_submit_target_class . "').append(jQuery('<div>').attr('class','." . $this->ajax_submit_target_class . "-loading').css('text-align','center').css('margin-top','100px').css('margin-bottom','100px').append(jQuery('<i>').addClass('icon icon-repeat icon-spin icon-4x')))

				";

                $this->ajax_datatype = 'json';
                //the response is json
                $on_success_script = "
				jQuery('." . $this->ajax_submit_target_class . "').html(data.html);
				var script = $.cresenity.base64.decode(data.js);

				eval(script);
				jQuery('." . $this->ajax_submit_target_class . "').removeClass('loading');
				jQuery('." . $this->ajax_submit_target_class . "').data('xhr',false);
				if(jQuery('." . $this->ajax_submit_target_class . "').find('.prettyprint').length>0) {
					window.prettyPrint && prettyPrint();
				}
				";
            }

            $validation_if_open = '';
            $validation_if_close = '';

            if ($this->validation) {
                $validation_if_open = "if ($('#" . $this->id . "').validationEngine('validate') ) {";
                $validation_if_close = "					} else {
						$('#" . $this->id . " .confirm').removeAttr('data-submitted');
					}
                ";
            }
            $js->appendln("
				$('#" . $this->id . " input[type=submit]').click(function() {
					$('input[type=submit]', $(this).parents('form')).removeAttr('clicked');
					$(this).attr('clicked', 'true');
				});
				$('#" . $this->id . "').submit(function(event) {

                    var fileupload = $('.control-fileupload');
                    var error = 0;
                    fileupload.each(function() {
                        $(this).children().each(function() {
                            if($(this).hasClass('loading')) {
                                alert('There\'s any file still uploading');
                                error++;
                            }
                        })
                    });
                    if($('#" . $this->id . " .fileupload-preview.loading').length>0) {
                        alert('There\'s any file still uploading');
                        error++;
                    }
                    if($('#" . $this->id . " .multi-image-ajax-file.loading').length>0) {
                        alert('There\'s any file still uploading');
                        error++;
                    }

                    if (error > 0) {
                        $('#" . $this->id . " .confirm').removeAttr('data-submitted');
                        return false;
                    }

					" . $validation_if_open . "
						if($('#" . $this->id . "').hasClass('loading')) return false;
						//don't do it again if still loading
						$('#" . $this->id . "').addClass('loading');
						$('#" . $this->id . "').find('*').addClass('disabled');
						" . $ajax_process_without_upload . "



						var form_ajax_url = $('#" . $this->id . "').attr('action');
						if(!form_ajax_url) form_ajax_url = '" . $ajax_url . "';
						var options = {
							url: form_ajax_url,
							dataType: '" . $this->ajax_datatype . "',
							type: '" . $this->method . "',
							beforeSubmit: function(arr, form, options) {
								" . $upload_progress_before_submit . '
							},
							uploadProgress: function(event, position, total, percentComplete) {
								' . $upload_progress_upload . "
								//console.log(percentVal, position, total);
							},
							success: function(data) {
                                                            $.cresenity._handle_response(data,function() {
                                                                    $('#" . $this->id . "').find('*').removeClass('disabled');
                                                                    $('#" . $this->id . "').removeClass('loading');
                                                                    " . $upload_progress_success . '
                                                                    ' . $ajax_process_done_script . '
                                                                    ' . $on_success_script . "
                                                                });
                                                                //do callback


							},

							error: function(xhr, status, error) {
								$('#" . $this->id . "').find('*').removeClass('disabled');
								" . $ajax_process_done_script . "
								$('#" . $this->id . "').removeClass('loading');
								$.cresenity.message('error','[AJAX] ' + status + ' - Server reponse is: ' + xhr.responseText);
								$('#" . $this->id . "').find('*').removeClass('disabled');
							}
						}
						" . $on_before_submit . '
						' . $this->action_before_submit . "
						$('#" . $this->id . "').ajaxSubmit(options);
					" . $validation_if_close . '
					//never submit form
					return false;
				});
			');
        } else {
            $js->appendln('//Form validation')->br();
            $strvalidation = '';
            if ($this->validation) {
                $strvalidation = "$('#" . $this->id . "').validationEngine();";
            }

            $js->appendln("
                    $('#" . $this->id . "').submit(function(event) {

                        var fileupload = $('.control-fileupload');
                        var error = 0;
                        fileupload.each(function() {
                            $(this).children().each(function() {
                                if($(this).hasClass('loading')) {
                                    alert('There\'s any file still uploading');
                                    error++;
                                }
                            })
                        });
                        if($('#" . $this->id . " .fileupload-preview.loading').length>0) {
                            alert('There\'s any file still uploading');
                            error++;
                        }
                        if($('#" . $this->id . " .multi-image-ajax-file.loading').length>0) {
                            alert('There\'s any file still uploading');
                            error++;
                        }
                        if (error > 0) {
                            $('#" . $this->id . " .confirm').removeAttr('data-submitted');
                            return false;
                        }

                    });
                ");

            $js->appendln('
                ' . $strvalidation . "
                $('#" . $this->id . "').bind('jqv.form.result', function(event , errorFound){
                    if(errorFound) {
                            $('#" . $this->id . " .confirm').removeAttr('data-submitted');
                    }
                    else {")->br();
            if (strlen($this->action_before_submit) > 0) {
                $js->appendln($this->action_before_submit);
            }
            $js->appendln('
                        }
                    });
                ')->br();
        }

        if ($this->auto_set_focus) {
            $js->appendln("
				$('#" . $this->id . "').find(':input:enabled:visible:first:not(.datepicker)').focus();
			");
        }
        $js->appendln($this->js_child($js->get_indent()))->br();

        return $js->text();
    }
}
