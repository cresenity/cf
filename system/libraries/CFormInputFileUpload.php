<?php
/**
 * @deprecated since 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputFileUpload extends CFormInput {
    protected $applyjs;

    protected $uniqid;

    protected $removeLink;

    protected $disabled;

    protected $files;

    protected $link;

    protected $custom_control;

    protected $custom_control_value;

    public function __construct($id) {
        parent::__construct($id);

        $this->removeLink = 'true';
        $this->type = 'fileupload';
        $this->applyjs = 'fileupload';
        $this->uniqid = uniqid();
        $this->files = [];
        $this->custom_control = [];
        $this->custom_control_value = [];
        $this->link = '';
    }

    public static function factory($id) {
        return new CFormInputFileUpload($id);
    }

    public function set_applyjs($applyjs) {
        $this->applyjs = $applyjs;

        return $this;
    }

    public function set_removeLink($bool) {
        $this->removeLink = $bool;

        return $this;
    }

    public function add_file($file_url, $input_name = '') {
        $arr = [];
        $arr['input_name'] = $input_name;
        $arr['file_url'] = $file_url;

        $this->files[] = $arr;

        return $this;
    }

    public function add_custom_control($control, $input_name, $input_label) {
        $arr = [];
        $arr['control'] = $control;
        $arr['input_name'] = $input_name;
        $arr['input_label'] = $input_label;
        $this->custom_control[] = $arr;

        return $this;
    }

    public function add_custom_control_value($input_name, $control_name, $input_value) {
        $this->custom_control_value[$input_name][$control_name] = $input_value;

        return $this;
    }

    public function set_link($value) {
        $this->link = $value;

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->set_indent($indent);
        $disabled = '';
        if ($this->disabled) {
            $disabled = ' disabled="disabled"';
        }
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
        $div_id = 'div_fileupload_' . $this->id;
        $html->appendln('
                <style>
                    #' . $div_id . ' {
                        border: 2px dashed #CDCDCD;
                        margin-left: 0px;
                        padding: 10px 10px;
                        width: 100%;
                        min-height: 100px;
                    }
                    #' . $div_id . '.ondrag {
                        border: 5px dashed #CDCDCD;
                        background-color: rgba(0, 0, 0, 0.05);
                    }
                    #' . $div_id . ' div.container-file-upload {
                        border: 1px solid #ddd;
                        margin: 6px 6px;
                        width: 200px;
                        height: auto;
                        float: left;
                        text-align: center;
                        position: relative;
                        border-radius: 4px;
                        padding:4px;
                    }
                    #' . $div_id . ' .div-img{
                        border: 0px;
                        width: auto;
                        height: 100px;
                        margin: 10px 0px;
                        position:relative;
                    }
                    #' . $div_id . ' div img {
                        position: absolute;
                        width: auto;
                        max-width: 100%;
                        height: auto;
                        max-height: 100%;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        margin: auto;
                        min-height: 1px;
                    }
                    #' . $div_id . '_description {
                        text-align: center;
                        display: block;
                        font-size: 20px;
                        margin: 15px 0;
                    }
                    #' . $div_id . '_message {
                        margin-left: 0px;
                        display: none;
                        font-size: 20px;
                    }
                    .' . $div_id . '_file.loading .' . $div_id . '_loading {
                        display: block;
                    }
                    .' . $div_id . '_file .' . $div_id . '_loading {
                        display: none;
                    }
                    .' . $div_id . '_loading {
                        position: absolute;
                        top: 0;
                        left: 0;
                        z-index: 1000;
                        background-color: rgba(0, 0, 0, 0.5);
                    }
                    .' . $div_id . '_remove {
                        cursor: pointer;
                    }
                    .div-custom-control{
                        margin-top:10px;
                    }
                    #' . $div_id . '_btn_upload {
                        width: 100%;
                        margin: 15px 0;
                    }
                    .div-custom-control label{
                        display:inline-block;
                    }
                    .div-custom-control input[type="text"]{
                        display:inline-block;
                        width:auto;
                    }

                    @media (min-width: 768px) {
                        #' . $div_id . '_btn_upload {
                            display: none;
                        }
                    }
                </style>
                <input id="' . $div_id . '_input_temp" type="file" name="' . $div_id . '_input_temp[]" multiple style="display:none;">
                <div id="' . $div_id . '_message" class="row alert alert-danger fade in">
                </div>
                <div id="' . $div_id . '_description">' . clang::__('Click or Drop Files On Box Below') . '</div>
                <div id="' . $div_id . '" class="row control-fileupload">
                ');
        foreach ($this->files as $f) {
            $input_name = carr::get($f, 'input_name');
            $file_url = carr::get($f, 'file_url');
            //<input id="' . $div_id . '_input_'.$ii.'" class="'.$div_id.'_i" type="file" name="'.$this->name.'['.$input_name.']" style="display:none;">
            $html->appendln('
                <div class="' . $div_id . '_file container-file-upload">
                    <div class="div-img">
                        <img src="' . $file_url . '" />
                        <input type="hidden" name="' . $this->name . '[' . $input_name . ']" value="">
                    </div>
            ');
            foreach ($this->custom_control as $cc) {
                $control = carr::get($cc, 'control');
                $control_name = carr::get($cc, 'input_name');
                $control_label = carr::get($cc, 'input_label');
                //get value
                $control_value = carr::get($this->custom_control_value, $input_name, []);
                $value = carr::get($control_value, $control_name);
                $html->appendln('
                        <div class="div-custom-control">
                            <label>' . $control_label . ' :</label><input type="' . $control . '" name="' . $this->name . '_custom_control[' . $input_name . '][' . $control_name . ']" value="' . $value . '"  >
                        </div>
                    ');
            }
            $html->appendln('
                        <a class="' . $div_id . '_remove">Remove</a>
                </div>
            ');
        }

        $html->appendln('
            </div>
        ');
        $html->appendln('
            <div>
                <div id="' . $div_id . '_btn_upload" class="btn btn-success">' . clang::__('Upload Image') . '</div>
            </div>
        ');

        return $html->text();
    }

    public function js($indent = 0) {
        $ajax_url = CAjaxMethod::factory()->set_type('fileupload')
            ->set_data('input_name', $this->name)
            ->makeurl();

        $div_id = 'div_fileupload_' . $this->id;
        $js = new CStringBuilder();
        $js->set_indent($indent);
        if (empty($ajax_url)) {
            throw new Exception('Link is empty', 1);
        }
        if ($this->applyjs == 'fileupload') {
            $js->appendln('
                var index=0;
                //var description = $("#' . $div_id . '_description");

                $("#' . $div_id . '_btn_upload").click(function() {
                    $( "#' . $div_id . '_input_temp" ).trigger("click");
                })

                $(this).on({
                    "dragover dragenter": function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                    },
                    "drop": function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                    },
                })
                $(".container-file-upload").click(function(e){
                        e.preventDefault();
                        e.stopPropagation();
                });
                // Remove File
                function file_upload_remove(e) {

                    $( ".' . $div_id . '_remove" ).click(function(e) {
                        e.preventDefault();
                        e.stopPropagation();


                        $(this).parent().remove();
                    })
                }

                file_upload_remove();
                $( "#' . $div_id . '" ).sortable();

                // Add Image by Drag & Drop
                $( "#' . $div_id . '" ).on({
                    "dragover dragenter": function(e) {
                        $(this).addClass("ondrag");
                    },
                    "dragleave dragend": function(e) {
                        $(this).removeClass("ondrag");
                    },
                    "drop": function(e) {
                        $(this).removeClass("ondrag");
                        $( "#' . $div_id . '" ).sortable();
                        var dataTransfer = e.originalEvent.dataTransfer;
                        if( dataTransfer && dataTransfer.files.length) {
                            e.preventDefault();
                            e.stopPropagation();
                            //$("#' . $div_id . '_description").remove();
                            $.each( dataTransfer.files, function(i, file) {
                                var reader = new FileReader();
                                reader.onload = $.proxy(function(file, fileList, event) {

                                    var img = file.type.match("image.*") ? "<img src=" + event.target.result + " /> " : "";
                                    var div = $("<div>").addClass("' . $div_id . '_file container-file-upload");
                                    div.click(function(e){
                                        e.preventDefault();
                                        e.stopPropagation();
                                    });
                                    var div_img=$("<div>").addClass("div-img");
                                    div_img.append(img);
                                    div.append(div_img);
            ');
            foreach ($this->custom_control as $cc) {
                $control = carr::get($cc, 'control');
                $control_name = carr::get($cc, 'input_name');
                $control_label = carr::get($cc, 'input_label');
                $js->appendln('
                    var div_cc=$("<div>").addClass("div-custom-control");
                    var cc_label=$("<label>").html("' . $control_label . ' :");
                    var cc=$("<input type=\"' . $control . '\" name=\"' . $this->name . '_custom_control["+index+"][' . $control_name . ']\">");
                    div_cc.append(cc_label);
                    div_cc.append(cc);
                    div.append(div_cc);
                ');
            }
            if ($this->removeLink) {
                $js->appendln('
                                    var remove = $("<a>").addClass("' . $div_id . '_remove").html("Remove");
                                    div.append(remove);
                ');
            }
            $js->appendln('
                                    div.append("<img class=\"' . $div_id . '_loading\" src=\"' . curl::base() . 'media/img/ring.gif\" />");
                                    fileList.append(div.addClass("loading"));
                                    file_upload_remove();

                                    var data = new FormData();
                                    data.append("' . $this->name . '[]", file);
                                    var xhr = new XMLHttpRequest();
                                    xhr.onreadystatechange = function() {
                                        if (this.readyState == 4 && this.status == 200) {
                                            div.removeClass("loading");
                                            div.append("<input type=\"hidden\" name=\"' . $this->name . '["+index+"]\" value="+ this.responseText +">");
                                            index++;
                                        } else if (this.readyState == 4 && this.status != 200) {
                                            //div.remove();
                                        }
                                    };
                                    xhr.open("post", "' . $ajax_url . '");
                                    xhr.send(data);
                                }, this, file, $("#' . $div_id . '"));
                                reader.readAsDataURL(file);
                            });
                        }
                    }
                })

                // Add Image by Click
                $( "#' . $div_id . '" ).click(function() {
                    $( "#' . $div_id . '_input_temp" ).trigger("click");
                })
                $( "#' . $div_id . '_input_temp" ).change(function(e) {
                    //$("#' . $div_id . '_description").remove();
                    $.each(e.target.files, function(i, file) {
                        var reader = new FileReader();
                        reader.onload = $.proxy(function(file, fileList, event) {

                            var img = file.type.match("image.*") ? "<img src=" + event.target.result + " /> " : "";
                            var div = $("<div>").addClass("' . $div_id . '_file container-file-upload");
                            div.click(function(e){
                                e.preventDefault();
                                e.stopPropagation();
                            });
                            var div_img=$("<div>").addClass("div-img");
                            div_img.append(img);
                            div.append(div_img);
            ');
            foreach ($this->custom_control as $cc) {
                $control = carr::get($cc, 'control');
                $control_name = carr::get($cc, 'input_name');
                $control_label = carr::get($cc, 'input_label');
                $js->appendln('
                    var div_cc=$("<div>").addClass("div-custom-control");
                    var cc_label=$("<label>").html("' . $control_label . ' :");
                    var cc=$("<input type=\"' . $control . '\" name=\"' . $this->name . '_custom_control["+index+"][' . $control_name . ']\">");
                    div_cc.append(cc_label);
                    div_cc.append(cc);
                    div.append(div_cc);
                ');
            }
            if ($this->removeLink) {
                $js->appendln('
                            var remove = $("<a>").addClass("' . $div_id . '_remove").html("Remove");
                            div.append(remove);
                ');
            }
            $js->appendln('
                            div.append("<img class=\"' . $div_id . '_loading\" src=\"' . curl::base() . 'media/img/ring.gif\" />");
                            fileList.append(div.addClass("loading"));
                            file_upload_remove();

                            var data = new FormData();
                            data.append("' . $this->name . '[]", file);
                            var xhr = new XMLHttpRequest();
                            xhr.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    div.removeClass("loading");
                                    div.append("<input type=\"hidden\" name=\"' . $this->name . '["+index+"]\" value="+ this.responseText +">");
                                    index++;
                                } else if (this.readyState == 4 && this.status != 200) {
                                    //div.remove();
                                }
                            };
                            xhr.open("post", "' . $ajax_url . '");
                            xhr.send(data);
                        }, this, file, $("#' . $div_id . '"));
                        reader.readAsDataURL(file);
                    })
                    $(this).val("");
                })
            ');
        }

        return $js->text();
    }
}
