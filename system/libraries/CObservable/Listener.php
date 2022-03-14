<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Sep 1, 2018, 3:43:55 PM
 */
class CObservable_Listener extends CObservable_ListenerAbstract {
    use CTrait_Compat_Listener;

    protected $confirm;

    protected $confirm_message;

    protected $noDouble;

    public function __construct($owner, $event = 'click') {
        parent::__construct($owner);
        $this->confirm = false;
        $this->confirm_message = '';
        $this->no_double = false;
        $this->event = $event;
    }

    public static function factory($owner, $event) {
        return new CObservable_Listener($owner, $event);
    }

    public function setConfirm($bool) {
        $this->confirm = $bool;

        return $this;
    }

    public function setNoDouble($bool) {
        $this->no_double = $bool;

        return $this;
    }

    public function getEvent() {
        return $this->event;
    }

    public function setConfirmMessage($message) {
        $this->confirm_message = $message;

        return $this;
    }

    public function getBodyJs() {
        $startScript = "
            var thiselm=jQuery(this);
            var clicked = thiselm.attr('data-clicked');
        ";
        if ($this->no_double) {
            $startScript .= '
                if(clicked) return false;
            ';
        }
        $startScript .= "
            thiselm.attr('data-clicked','1');
        ";
        $handlersScript = '';
        foreach ($this->handlers as $handler) {
            $handlersScript .= $handler->js();
        }
        $confirmStartScript = '';
        $confirmEndScript = '';
        if ($this->confirm) {
            $confirm_message = $this->confirm_message;
            if (strlen($confirm_message) == 0) {
                $confirm_message = c::__('Are you sure ?');
            }
            $confirmStartScript = "
                window.cresenity.confirm({owner:thiselm, message:'" . c::e($confirm_message) . "',confirmCallback: function(confirmed) {
                    if(confirmed) {
            ";

            $confirmEndScript = "
                    } else {
                        thiselm.removeAttr('data-clicked');
                    }
                    setTimeout(function() {
                        var modalExists = $('.modal:visible').length > 0;
                        if (!modalExists) {
                            $('body').removeClass('modal-open');
                        } else {
                            $('body').addClass('modal-open');
                        }
                    },750);
                }});
            ";
        }
        $compiledJs = $startScript . $confirmStartScript . $handlersScript . $confirmEndScript;

        return $compiledJs;
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->setIndent($indent);

        $compiledJs = $this->getBodyJs();

        $eventParameterImploded = implode(',', $this->eventParameters);
        if ($this->event == 'lazyload') {
            $js->append("
                jQuery(window).ready(function() {
                    if (jQuery('#" . $this->owner . "')[0].getBoundingClientRect().top < (jQuery(window).scrollTop() + jQuery(window).height())) {
                        " . $compiledJs . "
                    }
                });
                jQuery(window).scroll(function() {
                    if (jQuery('#" . $this->owner . "')[0].getBoundingClientRect().top < (jQuery(window).scrollTop() + jQuery(window).height())) {
                        " . $compiledJs . '
                    }
                });
            ');
        } else {
            $js->append("
                jQuery('#" . $this->owner . "')." . $this->event . '(function(' . $eventParameterImploded . ') {
                    ' . $compiledJs . '
                });
            ');
        }

        return $js->text();
    }
}
