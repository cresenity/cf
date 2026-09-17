<?php

defined('SYSPATH') or die('No direct access allowed.');
/**
 * PHP Excel library. Helper class to make spreadsheet creation easier.
 *
 * @author     Flynsarmy
 * @website    http://www.flynsarmy.com/
 *
 * @license    TEH FREEZ
 *
 * @deprecated 2.0
 */
require_once dirname(__FILE__) . '/Lib/fpdf/fpdf.php';

//@codingStandardsIgnoreStart
class CPDF extends FPDF {
    private $header_enabled = true;

    private $footer_enabled = true;

    private $param = [];

    private $header_callback_func = '';

    private $footer_callback_func = '';

    public function GetParam() {
        return $this->param;
    }

    public function SetParam($p) {
        $this->param = $p;

        return $this;
    }

    public function HeaderEnabled($bool) {
        $header_enabled = $bool;

        return $this;
    }

    public function HeaderCallback($func) {
        $this->header_callback_func = $func;

        return $this;
    }

    public function FooterEnabled($bool) {
        $header_enabled = $bool;

        return $this;
    }

    public function FooterCallback($func) {
        $this->footer_callback_func = $func;

        return $this;
    }

    public function Header() {
        if ($this->header_enabled) {
            if (($this->header_callback_func) != null) {
                $v = CFunction::factory($this->header_callback_func)
                    ->addArg($this)
                    ->execute();
            }
        }
    }

    public function Footer() {
        if ($this->footer_enabled) {
            if (($this->footer_callback_func) != null) {
                $v = CFunction::factory($this->footer_callback_func)
                    ->addArg($this)
                    ->execute();

                return $v;
            }
        }
    }

    public static function factory($o = 'P', $u = 'mm', $s = 'A4') {
        return new CPDF($o, $u, $s);
    }

    public function MultiCellLine($w, $h, $txt, $border = 0, $align = 'J', $fill = false) {
        // Output text with automatic or explicit line breaks
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $b = 0;
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false) {
                    $b2 .= 'L';
                }
                if (strpos($border, 'R') !== false) {
                    $b2 .= 'R';
                }
                $b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        $line = 0;
        while ($i < $nb) {
            // Get next character
            $c = $s[$i];
            if ($c == "\n") {
                // Explicit line break
                $line++;
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl == 2) {
                    $b = $b2;
                }

                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                    if ($this->ws > 0) {
                        $this->ws = 0;
                    }
                    $line++;
                } else {
                    $line++;
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl == 2) {
                    $b = $b2;
                }
            } else {
                $i++;
            }
        }
        // Last chunk

        if ($border && strpos($border, 'B') !== false) {
            $b .= 'B';
        }
        $line++;

        return $line;
    }
}
//@codingStandardsIgnoreEnd
