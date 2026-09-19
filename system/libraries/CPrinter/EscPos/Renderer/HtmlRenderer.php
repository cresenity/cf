<?php

class CPrinter_EscPos_Renderer_HtmlRenderer extends CPrinter_EscPos_RendererAbstract {
    protected $onPrintMode;

    protected $onEmphasized;

    protected $onJustification;

    protected $html;

    /**
     * Jumlah byte parameter perintah ESC yang tidak dirender, supaya tidak bocor sebagai teks.
     *
     * @var array
     */
    protected static $escParameterLength = [
        '-' => 1, '2' => 0, '3' => 1, 'M' => 1, 'G' => 1, 't' => 1, '{' => 1, 'r' => 1,
        'J' => 1, 'K' => 1, 'e' => 1, 'R' => 1, ' ' => 1, 'i' => 0, 'm' => 0, 'p' => 3, '%' => 1,
    ];

    /**
     * @return string
     */
    public function render() {
        $data = $this->data;

        $parser = new CPrinter_EscPos_Parser($data);
        $newData = '';
        $this->onPrintMode = false;
        $this->onEmphasized = false;
        $this->onJustification = false;
        $this->html = '';
        $parser->on(CPrinter_EscPos_Parser::EVENT_CHAR, function (CPrinter_EscPos_Parser $parser) {
            $this->html .= $parser->getCurrentChar();
        });

        $parser->on(CPrinter_EscPos_Parser::EVENT_ESC, function (CPrinter_EscPos_Parser $parser) {
            $parser->advance();
            $char = $parser->getCurrentChar();
            if ($char == '@') {
                //initialize, do nothing
            } elseif ($char == 'E') {
                $this->handleEmphasize($parser);
            } elseif ($char == '!') {
                $this->handlePrintMode($parser);
            } elseif ($char == 'a') {
                $this->handleJustification($parser);
            } elseif ($char == 'd') {
                $this->handleFeed($parser);
            } else {
                $this->skipParameters($parser, isset(self::$escParameterLength[$char]) ? self::$escParameterLength[$char] : 0);
            }
        });
        $parser->on(CPrinter_EscPos_Parser::EVENT_GROUP_SEPARATOR, function (CPrinter_EscPos_Parser $parser) {
            $parser->advance();
            $char = $parser->getCurrentChar();
            if ($char == 'k') {
                $this->handleBarcode($parser);
            }
            //do nothing
        });

        $parser->on(CPrinter_EscPos_Parser::EVENT_FEED_FORM, function (CPrinter_EscPos_Parser $parser) {
            //do nothing
        });
        $parser->on(CPrinter_EscPos_Parser::EVENT_END, function (CPrinter_EscPos_Parser $parser) {
            if ($this->onPrintMode || $this->onEmphasized) {
                $this->html .= '</span>';
            }
        });
        $parser->parse();

        return $this->html;
        //replace initialize
        // $data = str_replace(CPrinter_EscPos::ESC . '@', '', $data);

        //replace emphasis
        //$this->addCode(CPrinter_EscPos::ESC . 'E' . ($on ? chr(1) : chr(0)));
        // $openEmphasis = CPrinter_EscPos::ESC . 'E' . chr(1);
        // $closeEmphasis = CPrinter_EscPos::ESC . 'E' . chr(0);
        // $regexEmphasis = '#' . $openEmphasis . '.+?' . $closeEmphasis . '#ims';
        // $regexEmphasis = str_replace(chr(0), '\x00', $regexEmphasis);
        // $data = preg_replace_callback($regexEmphasis, function ($matches) {
        //     $match = carr::get($matches, 0);
        //     $match = $this->replaceWithSpan($match, CPrinter_EscPos::ESC . 'E' . chr(1), CPrinter_EscPos::ESC . 'E' . chr(0), 'font-weight:bold');

        //     return $match;
        // }, $data);

        // //replace mode
        // $allModes = CPrinter_EscPos::MODE_FONT_B | CPrinter_EscPos::MODE_EMPHASIZED | CPrinter_EscPos::MODE_DOUBLE_HEIGHT | CPrinter_EscPos::MODE_DOUBLE_WIDTH | CPrinter_EscPos::MODE_UNDERLINE;
        // $data = $this->handlePrintMode($data);
        // $data = $this->handleJustification($data);

        // $data = $this->handleBarcode($data);

        return $data;
    }

    /**
     * ESC d n: cetak dan umpan n baris.
     *
     * @param CPrinter_EscPos_Parser $parser
     *
     * @return void
     */
    protected function handleFeed(CPrinter_EscPos_Parser $parser) {
        $parser->advance();
        $lines = ord((string) $parser->getCurrentChar());
        $this->html .= str_repeat(CPrinter_EscPos::LF, max(1, $lines));
    }

    /**
     * @param CPrinter_EscPos_Parser $parser
     * @param int                    $length
     *
     * @return void
     */
    protected function skipParameters(CPrinter_EscPos_Parser $parser, $length) {
        for ($i = 0; $i < $length; $i++) {
            $parser->advance();
        }
    }

    protected function handleEmphasize(CPrinter_EscPos_Parser $parser) {
        //emphasis, check for open and closed
        $parser->advance();
        $nextChar = $parser->getCurrentChar();
        if ($nextChar == chr(1)) {
            $this->html .= '<span style="font-weight:bold">';
            $this->onEmphasized = true;
        } else {
            if ($this->onEmphasized) {
                $this->html .= '</span>';
            }
        }
    }

    protected function handleJustification(CPrinter_EscPos_Parser $parser) {
        $parser->advance();
        $nextChar = $parser->getCurrentChar();
        $isJustifyCenter = (ord($nextChar) & CPrinter_EscPos::JUSTIFY_CENTER) == CPrinter_EscPos::JUSTIFY_CENTER;
        $isJustifyRight = (ord($nextChar) & CPrinter_EscPos::JUSTIFY_RIGHT) == CPrinter_EscPos::JUSTIFY_RIGHT;
        $isJustifyLeft = (ord($nextChar) & CPrinter_EscPos::JUSTIFY_LEFT) == CPrinter_EscPos::JUSTIFY_LEFT;
        if ($this->onJustification) {
            $this->html .= '</span>';
        }
        $span = '<span style="display:inline-block;';
        if ($isJustifyCenter) {
            $span .= 'text-align:center;';
        }
        if ($isJustifyRight) {
            $span .= 'text-align:right;';
        }
        if ($isJustifyLeft) {
            $span .= 'text-align:left;';
        }
        $span .= '">';
        $this->html .= $span;
        $this->onJustification = true;
    }

    protected function handlePrintMode(CPrinter_EscPos_Parser $parser) {
        //printmode
        $parser->advance();
        $nextChar = $parser->getCurrentChar();
        $isModeFontB = ord($nextChar) & CPrinter_EscPos::MODE_FONT_B == CPrinter_EscPos::MODE_FONT_B;
        $isModeFontA = !$isModeFontB;
        // cdbg::dd(ord($nextChar) & CPrinter_EscPos::MODE_EMPHASIZED);
        $isModeEmphasized = (ord($nextChar) & CPrinter_EscPos::MODE_EMPHASIZED) == CPrinter_EscPos::MODE_EMPHASIZED;
        $isModeDoubleHeight = (ord($nextChar) & CPrinter_EscPos::MODE_DOUBLE_HEIGHT) == CPrinter_EscPos::MODE_DOUBLE_HEIGHT;
        $isModeDoubleWidth = (ord($nextChar) & CPrinter_EscPos::MODE_DOUBLE_WIDTH) == CPrinter_EscPos::MODE_DOUBLE_WIDTH;
        $isModeUnderline = (ord($nextChar) & CPrinter_EscPos::MODE_UNDERLINE) == CPrinter_EscPos::MODE_UNDERLINE;
        if ($this->onPrintMode) {
            $this->html .= '</span>';
        }
        $span = '<span style="';
        if ($isModeFontB) {
        }
        if ($isModeEmphasized) {
            $span .= 'font-weight:bold;';
        }

        if ($isModeUnderline) {
            $span .= 'text-decoration:underline;';
        }
        $span .= '">';
        $this->html .= $span;
        $this->onPrintMode = true;
    }

    protected function handleBarcode(CPrinter_EscPos_Parser $parser) {
        $supportsBarcodeB = $this->profile ? $this->profile->getSupportsBarcodeB() : false;
        $barcodeTypeMap = [
            CPrinter_EscPos::BARCODE_CODE39 => \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_39,
            CPrinter_EscPos::BARCODE_CODE128 => \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128,
            CPrinter_EscPos::BARCODE_CODE93 => \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_93,
            CPrinter_EscPos::BARCODE_CODABAR => \Picqer\Barcode\BarcodeGenerator::TYPE_CODABAR,
            CPrinter_EscPos::BARCODE_ITF => \Picqer\Barcode\BarcodeGenerator::TYPE_ITF_14,
            CPrinter_EscPos::BARCODE_UPCA => \Picqer\Barcode\BarcodeGenerator::TYPE_UPC_A,
            CPrinter_EscPos::BARCODE_UPCE => \Picqer\Barcode\BarcodeGenerator::TYPE_UPC_E,
            CPrinter_EscPos::BARCODE_JAN13 => \Picqer\Barcode\BarcodeGenerator::TYPE_EAN_13,
            CPrinter_EscPos::BARCODE_JAN8 => \Picqer\Barcode\BarcodeGenerator::TYPE_EAN_8,
        ];
        $parser->advance();
        $type = ord($parser->getCurrentChar());
        $generator = new Picqer\Barcode\BarcodeGeneratorHTML();
        $content = '';
        if (!$supportsBarcodeB) {
            $type += 65;
            $content = $parser->advanceUntil(CPrinter_EscPos::NUL);
        } else {
            $parser->advance();
            $len = ord($parser->getCurrentChar());
            $content = $parser->advanceFor($len);
        }
        $barcodeType = carr::get($barcodeTypeMap, $type);
        $barcode = $generator->getBarcode($content, $barcodeType, 2, 50);
        $this->html .= $barcode;
    }
}
