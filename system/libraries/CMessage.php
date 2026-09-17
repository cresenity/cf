<?php

 //@codingStandardsIgnoreStart
 /**
  * @deprecated 1.2 use CElement_Component_Alert
  * @see CElement_Component_Alert
  */
 class CMessage extends CElement {
     use CTrait_Compat_Message;
     protected $type;

     protected $message;

     public function __construct($id = '') {
         parent::__construct($id);

         $this->type = 'error';
         $this->message = '';
     }

     public function setType($type) {
         $this->type = $type;

         return $this;
     }

     public function setMessage($msg) {
         $this->message = $msg;

         return $this;
     }

     public static function factory($id = '') {
         return new CMessage($id);
     }

     public function html($indent = 0) {
         $html = new CStringBuilder();
         $html->setIndent($indent);
         $class = '';
         $icon = '';
         $header = '';
         switch ($this->type) {
            case 'warning':
                $icon = 'fa-warning';
                $class = ' alert-warning';
                $header = clang::__('Warning') . '!';

                break;
            case 'info':
                $icon = 'fa-info';
                $class = ' alert-info';
                $header = clang::__('Info') . '!';

                break;
            case 'success':
                $icon = 'fa-check';
                $class = ' alert-success';
                $header = clang::__('Success') . '!';

                break;
            default:
                $icon = 'fa-ban';
                $class = ' alert-error alert-danger';
                $header = clang::__('Error') . '!';

                break;
        }
         $html->appendln('<div class="alert alert-dismissible ' . $class . '" role="alert">')->incIndent()->br();

         $html->appendln('<a href="#" class="close" data-dismiss="alert">&times;</a>')->br();
         $html->appendln('<strong>' . $header . '</strong> ' . $this->message)->br();

         $html->decIndent()->appendln('</div>')->br();

         return $html->text();
     }

     public function js($indent = 0) {
         return '';
     }
 }
