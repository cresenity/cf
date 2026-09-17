<?php

defined('SYSPATH') or die('No direct access allowed.');
//@codingStandardsIgnoreStart
class CRawPrintBuilder {
    public $data = '';

    public $width = 80;

    public $height = 30;

    public $current_x = 0;

    public $current_y = 0;

    public $current_line = 0;

    public $current_page = 1;

    public $header = null;

    public $footer = null;

    public $show;

    private $driver = null;

    public function __construct($driver_name = null) {
        if ($driver_name == null) {
            $driver_name = 'LX300';
        }
        $driver_location = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'drivers' . DIRECTORY_SEPARATOR . 'CRawPrint' . DIRECTORY_SEPARATOR . $driver_name . EXT;

        require_once $driver_location;

        $this->driver = new $driver_name();
        $this->data = '';
    }

    public static function factory() {
        return new CRawPrintBuilder();
    }

    public function current_y() {
        return $this->current_y;
    }

    public function reset() {
        $this->current_y = 0;
        $this->data = '';
    }

    public function br() {
        //$this->data.=chr(13);
        $this->data .= "\r\n";
        $this->current_y++;

        return $this;
    }

    public function ff() {
        $this->data .= chr(12);

        return $this;
    }

    public function escape_code($code) {
        $this->data .= chr(27) . $code;

        return $this;
    }

    public function add_code($codex) {
        $this->data .= $codex;

        return $this;
    }

    public function space($n, $char = ' ') {
        $clean_char = $char;
        if (strlen($clean_char) > 1) {
            $clean_char = substr($clean_char, 0, 1);
        }
        for ($i = 0; $i < $n; $i++) {
            $this->data .= $clean_char;
        }

        return $this;
    }

    public function text($text, $width = null, $align = 'L', $spacing = ' ') {
        if ($align == 'C') {
            return $this->text_center($text, $width, $spacing);
        } elseif ($align == 'R') {
            return $this->text_right($text, $width, $spacing);
        } else {
            return $this->text_left($text, $width, $spacing);
        }
    }

    public function text_left($text, $width = null, $spacing = ' ') {
        if ($width == null) {
            $this->data .= $text;
        } else {
            $len = strlen($text);
            if ($len > $width) {
                $newtext = substr($text, 0, $width);
                $this->data .= $newtext;
            } else {
                $this->data .= $text;
                $this->space($width - $len, $spacing);
            }
        }

        return $this;
    }

    public function text_right($text, $width = null, $spacing = ' ') {
        if ($width == null) {
            $this->data .= $text;
        } else {
            $len = strlen($text);
            if ($len > $width) {
                $newtext = substr($text, 0, $width);
                $this->data .= $newtext;
            } else {
                $this->space($width - $len, $spacing);
                $this->data .= $text;
            }
        }

        return $this;
    }

    public function text_center($text, $width = null, $spacing = ' ') {
        if ($width == null) {
            $this->data .= $text;
        } else {
            $len = strlen($text);
            if ($len > $width) {
                $newtext = substr($text, 0, $width);
                $this->data .= $newtext;
            } else {
                $remain_space = $width - $len;
                $this->space(floor($remain_space / 2), $spacing);
                $this->data .= $text;
                $this->space(floor($remain_space / 2), $spacing);

                $this->space(($remain_space) % 2, $spacing);
            }
        }

        return $this;
    }

    public function render() {
        return $this->data;
    }

    //font style
    public function start_bold() {
        $this->add_code($this->driver->start_bold());
    }

    public function stop_bold() {
        $this->add_code($this->driver->stop_bold());
    }

    public function start_italic() {
        $this->add_code($this->driver->start_italic());
    }

    public function stop_italic() {
        $this->add_code($this->driver->stop_italic());
    }

    public function start_underline() {
        $this->add_code($this->driver->start_underline());
    }

    public function stop_underline() {
        $this->add_code($this->driver->stop_underline());
    }

    public function goto_next_page() {
        $this->add_code($this->driver->goto_next_page());
    }

    public function stop_goto_next_page() {
        $this->add_code($this->driver->stop_goto_next_page());
    }

    public function start_line() {
        $this->add_code($this->driver->start_line());
    }

    public function stop_line() {
        $this->add_code($this->driver->stop_line());
    }
}
