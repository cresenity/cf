<?php

/**
 * Main class for ESC/POS code generation.
 */
class CPrinter_EscPos_Printer {
    /**
     * @var null|CPrinter_EscPos_Contract_PrintBufferInterface
     *                                                         The printer's output buffer
     */
    protected $buffer;

    /**
     * @var CPrinter_EscPos_Contract_PrintConnectorInterface
     *                                                       Connector showing how to print to this printer
     */
    protected $connector;

    /**
     * @var CPrinter_EscPos_CapabilityProfile
     *                                        Profile showing supported features for this printer
     */
    protected $profile;

    /**
     * @var int
     *          Current character code table
     */
    protected $characterTable;

    /**
     * Construct a new print object.
     *
     * @param CPrinter_EscPos_Contract_PrintConnectorInterface $connector The PrintConnector to send data to. If not set, output is sent to standard output.
     * @param null|CPrinter_EscPos_CapabilityProfile           $profile   Supported features of this printer. If not set, the "default" CapabilityProfile will be used, which is suitable for Epson printers.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(CPrinter_EscPos_Contract_PrintConnectorInterface $connector, ?CPrinter_EscPos_CapabilityProfile $profile = null) {
        /* Set connector */
        $this->connector = $connector;

        /* Set capability profile */
        if ($profile === null) {
            $profile = CPrinter_EscPos_CapabilityProfile::load('default');
        }
        $this->profile = $profile;
        /* Set buffer */
        $buffer = new CPrinter_EscPos_PrintBuffer_EscposPrintBuffer();
        $this->buffer = null;
        $this->setPrintBuffer($buffer);
        $this->initialize();
    }

    /**
     * Print a barcode.
     *
     * @param string $content the information to encode
     * @param int    $type    The barcode standard to output. Supported values are
     *                        `Printer::BARCODE_UPCA`, `Printer::BARCODE_UPCE`, `Printer::BARCODE_JAN13`,
     *                        `Printer::BARCODE_JAN8`, `Printer::BARCODE_CODE39`, `Printer::BARCODE_ITF`,
     *                        `Printer::BARCODE_CODABAR`, `Printer::BARCODE_CODE93`, and `Printer::BARCODE_CODE128`.
     *                        If not specified, `Printer::BARCODE_CODE39` will be used. Note that some
     *                        barcode formats only support specific lengths or sets of characters, and that
     *                        available barcode types vary between printers.
     *
     * @throws InvalidArgumentException where the length or characters used in $content is invalid for the requested barcode format
     *
     * @return $this
     */
    public function barcode(string $content, int $type = CPrinter_EscPos::BARCODE_CODE39) {
        /* Validate input */
        self::validateInteger($type, 65, 73, __FUNCTION__, 'Barcode type');
        $len = strlen($content);
        switch ($type) {
            case CPrinter_EscPos::BARCODE_UPCA:
                self::validateInteger($len, 11, 12, __FUNCTION__, 'UPCA barcode content length');
                self::validateStringRegex($content, __FUNCTION__, '/^[0-9]{11,12}$/', 'UPCA barcode content');

                break;
            case CPrinter_EscPos::BARCODE_UPCE:
                self::validateIntegerMulti($len, [[6, 8], [11, 12]], __FUNCTION__, 'UPCE barcode content length');
                self::validateStringRegex($content, __FUNCTION__, '/^([0-9]{6,8}|[0-9]{11,12})$/', 'UPCE barcode content');

                break;
            case CPrinter_EscPos::BARCODE_JAN13:
                self::validateInteger($len, 12, 13, __FUNCTION__, 'JAN13 barcode content length');
                self::validateStringRegex($content, __FUNCTION__, '/^[0-9]{12,13}$/', 'JAN13 barcode content');

                break;
            case CPrinter_EscPos::BARCODE_JAN8:
                self::validateInteger($len, 7, 8, __FUNCTION__, 'JAN8 barcode content length');
                self::validateStringRegex($content, __FUNCTION__, '/^[0-9]{7,8}$/', 'JAN8 barcode content');

                break;
            case CPrinter_EscPos::BARCODE_CODE39:
                self::validateInteger($len, 1, 255, __FUNCTION__, 'CODE39 barcode content length'); // 255 is a limitation of the "function b" command, not the barcode format.
                self::validateStringRegex($content, __FUNCTION__, "/^([0-9A-Z \$\%\+\-\.\/]+|\*[0-9A-Z \$\%\+\-\.\/]+\*)$/", 'CODE39 barcode content');

                break;
            case CPrinter_EscPos::BARCODE_ITF:
                self::validateInteger($len, 2, 255, __FUNCTION__, 'ITF barcode content length'); // 255 is a limitation of the "function b" command, not the barcode format.
                self::validateStringRegex($content, __FUNCTION__, '/^([0-9]{2})+$/', 'ITF barcode content');

                break;
            case CPrinter_EscPos::BARCODE_CODABAR:
                self::validateInteger($len, 1, 255, __FUNCTION__, 'Codabar barcode content length'); // 255 is a limitation of the "function b" command, not the barcode format.
                self::validateStringRegex($content, __FUNCTION__, "/^[A-Da-d][0-9\$\+\-\.\/\:]+[A-Da-d]$/", 'Codabar barcode content');

                break;
            case CPrinter_EscPos::BARCODE_CODE93:
                self::validateInteger($len, 1, 255, __FUNCTION__, 'Code93 barcode content length'); // 255 is a limitation of the "function b" command, not the barcode format.
                self::validateStringRegex($content, __FUNCTION__, '/^[\\x00-\\x7F]+$/', 'Code93 barcode content');

                break;
            case CPrinter_EscPos::BARCODE_CODE128:
                self::validateInteger($len, 1, 255, __FUNCTION__, 'Code128 barcode content length'); // 255 is a limitation of the "function b" command, not the barcode format.
                // The CODE128 encoder is quite complex, so only a very basic header-check is applied here.
                self::validateStringRegex($content, __FUNCTION__, "/^\{[A-C][\\x00-\\x7F]+$/", 'Code128 barcode content');

                break;
        }

        if (!$this->profile->getSupportsBarcodeB()) {
            // A simpler barcode command which supports fewer codes
            self::validateInteger($type, 65, 71, __FUNCTION__);
            $this->connector->write(CPrinter_EscPos::GS . 'k' . chr($type - 65) . $content . CPrinter_EscPos::NUL);

            return $this;
        }
        // More advanced function B, used in preference
        $this->connector->write(CPrinter_EscPos::GS . 'k' . chr($type) . chr(strlen($content)) . $content);

        return $this;
    }

    /**
     * Print an image, using the older "bit image" command. This creates padding on the right of the image,
     * if its width is not divisible by 8.
     *
     * Should only be used if your printer does not support the graphics() command.
     * See also bitImageColumnFormat().
     *
     * @param CPrinter_EscPos_EscposImageAbstract $img  The image to print
     * @param int                                 $size Size modifier for the image. Must be either `CPrinter_EscPos::IMG_DEFAULT`
     *                                                  (default), or any combination of the `CPrinter_EscPos::IMG_DOUBLE_HEIGHT` and
     *                                                  `CPrinter_EscPos::IMG_DOUBLE_WIDTH` flags.
     *
     * @return $this
     */
    public function bitImage(CPrinter_EscPos_EscposImageAbstract $img, int $size = CPrinter_EscPos::IMG_DEFAULT) {
        self::validateInteger($size, 0, 3, __FUNCTION__);
        $rasterData = $img->toRasterFormat();
        $header = CPrinter_EscPos_Printer::dataHeader([$img->getWidthBytes(), $img->getHeight()], true);
        $this->connector->write(CPrinter_EscPos::GS . 'v0' . chr($size) . $header);
        $this->connector->write($rasterData);

        return $this;
    }

    /**
     * Print an image, using the older "bit image" command in column format.
     *
     * Should only be used if your printer does not support the graphics() or
     * bitImage() commands.
     *
     * @param CPrinter_EscPos_EscposImageAbstract $img  The image to print
     * @param int                                 $size Size modifier for the image. Must be either `CPrinter_EscPos::IMG_DEFAULT`
     *                                                  (default), or any combination of the `CPrinter_EscPos::IMG_DOUBLE_HEIGHT` and
     *                                                  `CPrinter_EscPos::IMG_DOUBLE_WIDTH` flags.
     *
     * @return $this
     */
    public function bitImageColumnFormat(CPrinter_EscPos_EscposImageAbstract $img, int $size = CPrinter_EscPos::IMG_DEFAULT) {
        $highDensityVertical = !(($size & CPrinter_EscPos::IMG_DOUBLE_HEIGHT) == CPrinter_EscPos::IMG_DOUBLE_HEIGHT);
        $highDensityHorizontal = !(($size & CPrinter_EscPos::IMG_DOUBLE_WIDTH) == CPrinter_EscPos::IMG_DOUBLE_WIDTH);
        // Experimental column format printing
        // This feature is not yet complete and may produce unpredictable results.
        $this->setLineSpacing(16); // 16-dot line spacing. This is the correct value on both TM-T20 and TM-U220
        // Header and density code (0, 1, 32, 33) re-used for every line
        $densityCode = ($highDensityHorizontal ? 1 : 0) + ($highDensityVertical ? 32 : 0);
        $colFormatData = $img->toColumnFormat($highDensityVertical);
        $header = CPrinter_EscPos_Printer::dataHeader([$img->getWidth()], true);
        foreach ($colFormatData as $line) {
            // Print each line, double density etc for printing are set here also
            $this->connector->write(CPrinter_EscPos::ESC . '*' . chr($densityCode) . $header . $line);
            $this->feed();
            // sleep(0.1); // Reduces the amount of trouble that a TM-U220 has keeping up with large images
        }
        $this->setLineSpacing(); // Revert to default line spacing

        return $this;
    }

    /**
     * Close the underlying buffer. With some connectors, the
     * job will not actually be sent to the printer until this is called.
     */
    public function close() {
        $this->connector->finalize();
    }

    /**
     * Cut the paper.
     *
     * @param int $mode  Cut mode, either Printer::CUT_FULL or Printer::CUT_PARTIAL. If not specified, `Printer::CUT_FULL` will be used.
     * @param int $lines Number of lines to feed
     *
     * @return $this
     */
    public function cut(int $mode = CPrinter_EscPos::CUT_FULL, int $lines = 3) {
        // TODO validation on cut() inputs
        $this->connector->write(CPrinter_EscPos::GS . 'V' . chr($mode) . chr($lines));

        return $this;
    }

    /**
     * Print and feed line / Print and feed n lines.
     *
     * @param int $lines Number of lines to feed
     *
     * @return $this
     */
    public function feed(int $lines = 1) {
        self::validateInteger($lines, 1, 255, __FUNCTION__);
        if ($lines <= 1) {
            $this->connector->write(CPrinter_EscPos::LF);
        } else {
            $this->connector->write(CPrinter_EscPos::ESC . 'd' . chr($lines));
        }

        return $this;
    }

    /**
     * Some printers require a form feed to release the paper. On most printers, this
     * command is only useful in page mode, which is not implemented in this driver.
     *
     * @return $this
     */
    public function feedForm() {
        $this->connector->write(CPrinter_EscPos::FF);

        return $this;
    }

    /**
     * Some slip printers require `ESC q` sequence to release the paper.
     *
     * @return $this
     */
    public function release() {
        $this->connector->write(CPrinter_EscPos::ESC . chr(113));

        return $this;
    }

    /**
     * Print and reverse feed n lines.
     *
     * @param int $lines number of lines to feed. If not specified, 1 line will be fed.
     *
     * @return $this
     */
    public function feedReverse(int $lines = 1) {
        self::validateInteger($lines, 1, 255, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::ESC . 'e' . chr($lines));

        return $this;
    }

    /**
     * @return int
     */
    public function getCharacterTable() {
        return $this->characterTable;
    }

    /**
     * @return CPrinter_EscPos_Contract_PrintBufferInterface
     */
    public function getPrintBuffer() {
        return $this->buffer;
    }

    /**
     * @return CPrinter_EscPos_Contract_PrintConnectorInterface
     */
    public function getPrintConnector() {
        return $this->connector;
    }

    /**
     * @return CPrinter_EscPos_CapabilityProfile
     */
    public function getPrinterCapabilityProfile() {
        return $this->profile;
    }

    /**
     * Print an image to the printer.
     *
     * Size modifiers are:
     * - Printer::IMG_DEFAULT (leave image at original size)
     * - Printer::IMG_DOUBLE_WIDTH
     * - Printer::IMG_DOUBLE_HEIGHT
     *
     * See the example/ folder for detailed examples.
     *
     * The functions bitImage() and bitImageColumnFormat() take the same
     * parameters, and can be used if your printer doesn't support the newer
     * graphics commands.
     *
     * @param CPrinter_EscPos_EscposImageAbstract $img  the image to print
     * @param int                                 $size Size modifier for the image. Must be either `Printer::IMG_DEFAULT`
     *                                                  (default), or any combination of the `Printer::IMG_DOUBLE_HEIGHT` and
     *                                                  `CPrinter_EscPos::IMG_DOUBLE_WIDTH` flags.
     *
     * @return $this
     */
    public function graphics(CPrinter_EscPos_EscposImageAbstract $img, int $size = CPrinter_EscPos::IMG_DEFAULT) {
        self::validateInteger($size, 0, 3, __FUNCTION__);
        $rasterData = $img->toRasterFormat();
        $imgHeader = CPrinter_EscPos_Printer::dataHeader([$img->getWidth(), $img->getHeight()], true);
        $tone = '0';
        $colors = '1';
        $xm = (($size & CPrinter_EscPos::IMG_DOUBLE_WIDTH) == CPrinter_EscPos::IMG_DOUBLE_WIDTH) ? chr(2) : chr(1);
        $ym = (($size & CPrinter_EscPos::IMG_DOUBLE_HEIGHT) == CPrinter_EscPos::IMG_DOUBLE_HEIGHT) ? chr(2) : chr(1);
        $header = $tone . $xm . $ym . $colors . $imgHeader;
        $this->wrapperSendGraphicsData('0', 'p', $header . $rasterData);
        $this->wrapperSendGraphicsData('0', '2');

        return $this;
    }

    /**
     * Initialize printer. This resets formatting back to the defaults.
     *
     * @return $this
     */
    public function initialize() {
        $this->connector->write(CPrinter_EscPos::ESC . '@');
        $this->characterTable = 0;

        return $this;
    }

    /**
     * Print a two-dimensional data code using the PDF417 standard.
     *
     * @param string $content          Text or numbers to store in the code
     * @param int    $width            Width of a module (pixel) in the printed code.
     *                                 Default is 3 dots.
     * @param int    $heightMultiplier Multiplier for height of a module.
     *                                 Default is 3 times the width.
     * @param int    $dataColumnCount  Number of data columns to use. 0 (default)
     *                                 is to auto-calculate. Smaller numbers will result in a narrower code,
     *                                 making larger pixel sizes possible. Larger numbers require smaller pixel sizes.
     * @param float  $ec               Error correction ratio, from 0.01 to 4.00. Default is 0.10 (10%).
     * @param int    $options          standard code Printer::PDF417_STANDARD with
     *                                 start/end bars, or truncated code Printer::PDF417_TRUNCATED with start bars only
     *
     * @throws Exception If this profile indicates that PDF417 code is not supported
     *
     * @return $this
     */
    public function pdf417Code(string $content, int $width = 3, int $heightMultiplier = 3, int $dataColumnCount = 0, float $ec = 0.10, int $options = CPrinter_EscPos::PDF417_STANDARD) {
        self::validateInteger($width, 2, 8, __FUNCTION__, 'width');
        self::validateInteger($heightMultiplier, 2, 8, __FUNCTION__, 'heightMultiplier');
        self::validateInteger($dataColumnCount, 0, 30, __FUNCTION__, 'dataColumnCount');
        self::validateFloat($ec, 0.01, 4.00, __FUNCTION__, 'ec');
        self::validateInteger($options, 0, 1, __FUNCTION__, 'options');
        if ($content == '') {
            return;
        }
        if (!$this->profile->getSupportsPdf417Code()) {
            // TODO use software rendering via a library instead
            throw new Exception('PDF417 codes are not supported on your printer.');
        }
        $cn = '0'; // Code type for pdf417 code
        // Select model: standard or truncated
        $this->wrapperSend2dCodeData(chr(70), $cn, chr($options));
        // Column count
        $this->wrapperSend2dCodeData(chr(65), $cn, chr($dataColumnCount));
        // Set dot sizes
        $this->wrapperSend2dCodeData(chr(67), $cn, chr($width));
        $this->wrapperSend2dCodeData(chr(68), $cn, chr($heightMultiplier));
        // Set error correction ratio: 1% to 400%
        $ec_int = (int) ceil(floatval($ec) * 10);
        $this->wrapperSend2dCodeData(chr(69), $cn, chr($ec_int), '1');
        // Send content & print
        $this->wrapperSend2dCodeData(chr(80), $cn, $content, '0');
        $this->wrapperSend2dCodeData(chr(81), $cn, '', '0');

        return $this;
    }

    /**
     * Generate a pulse, for opening a cash drawer if one is connected.
     * The default settings should open an Epson drawer.
     *
     * @param int $pin    0 or 1, for pin 2 or pin 5 kick-out connector respectively
     * @param int $on_ms  pulse ON time, in milliseconds
     * @param int $off_ms pulse OFF time, in milliseconds
     *
     * @return $this
     */
    public function pulse(int $pin = 0, int $on_ms = 120, int $off_ms = 240) {
        self::validateInteger($pin, 0, 1, __FUNCTION__);
        self::validateInteger($on_ms, 1, 511, __FUNCTION__);
        self::validateInteger($off_ms, 1, 511, __FUNCTION__);
        $pin_value = $pin + 48; // Character '0' or '1'.
        $on_value = intdiv($on_ms, 2);
        $off_value = intdiv($off_ms, 2);
        $this->connector->write(CPrinter_EscPos::ESC . 'p' . chr($pin_value) . chr($on_value) . chr($off_value));

        return $this;
    }

    /**
     * Print the given data as a QR code on the printer.
     *
     * @param string $content The content of the code. Numeric data will be more efficiently compacted.
     * @param int    $ec      Error-correction level to use. One of Printer::QR_ECLEVEL_L (default), Printer::QR_ECLEVEL_M, Printer::QR_ECLEVEL_Q or Printer::QR_ECLEVEL_H. Higher error correction results in a less compact code.
     * @param int    $size    Pixel size to use. Must be 1-16 (default 3)
     * @param int    $model   QR code model to use. Must be one of Printer::QR_MODEL_1, Printer::QR_MODEL_2 (default) or Printer::QR_MICRO (not supported by all printers).
     *
     * @return $this
     */
    public function qrCode(string $content, int $ec = CPrinter_EscPos::QR_ECLEVEL_L, int $size = 3, int $model = CPrinter_EscPos::QR_MODEL_2) {
        self::validateInteger($ec, 0, 3, __FUNCTION__);
        self::validateInteger($size, 1, 16, __FUNCTION__);
        self::validateInteger($model, 1, 3, __FUNCTION__);
        if ($content == '') {
            return $this;
        }
        if (!$this->profile->getSupportsQrCode()) {
            // TODO use software rendering via phpqrcode instead
            throw new Exception('QR codes are not supported on your printer.');
        }
        $cn = '1'; // Code type for QR code
        // Select model: 1, 2 or micro.
        $this->wrapperSend2dCodeData(chr(65), $cn, chr(48 + $model) . chr(0));
        // Set dot size.
        $this->wrapperSend2dCodeData(chr(67), $cn, chr($size));
        // Set error correction level: L, M, Q, or H
        $this->wrapperSend2dCodeData(chr(69), $cn, chr(48 + $ec));
        // Send content & print
        $this->wrapperSend2dCodeData(chr(80), $cn, $content, '0');
        $this->wrapperSend2dCodeData(chr(81), $cn, '', '0');

        return $this;
    }

    /**
     * Switch character table (code page) manually. Used in conjunction with textRaw() to
     * print special characters which can't be encoded automatically.
     *
     * @param int $table The table to select. Available code tables are model-specific.
     *
     * @return $this
     */
    public function selectCharacterTable(int $table = 0) {
        self::validateInteger($table, 0, 255, __FUNCTION__);
        $supported = $this->profile->getCodePages();
        if (!isset($supported[$table])) {
            throw new InvalidArgumentException("There is no code table $table allowed by this printer's capability profile.");
        }
        $this->characterTable = $table;
        if ($this->profile->getSupportsStarCommands()) {
            /* Not an ESC/POS command: STAR printers stash all the extra code pages under a different command. */
            $this->connector->write(CPrinter_EscPos::ESC . CPrinter_EscPos::GS . 't' . chr($table));

            return;
        }
        $this->connector->write(CPrinter_EscPos::ESC . 't' . chr($table));

        return $this;
    }

    /**
     * Select print mode(s).
     *
     * Several MODE_* constants can be OR'd together passed to this function's `$mode` argument. The valid modes are:
     *  - Printer::MODE_FONT_A
     *  - Printer::MODE_FONT_B
     *  - Printer::MODE_EMPHASIZED
     *  - Printer::MODE_DOUBLE_HEIGHT
     *  - Printer::MODE_DOUBLE_WIDTH
     *  - Printer::MODE_UNDERLINE
     *
     * @param int $mode The mode to use. Default is Printer::MODE_FONT_A, with no special formatting. This has a similar effect to running initialize().
     *
     * @return $this
     */
    public function selectPrintMode(int $mode = CPrinter_EscPos::MODE_FONT_A) {
        $allModes = CPrinter_EscPos::MODE_FONT_B | CPrinter_EscPos::MODE_EMPHASIZED | CPrinter_EscPos::MODE_DOUBLE_HEIGHT | CPrinter_EscPos::MODE_DOUBLE_WIDTH | CPrinter_EscPos::MODE_UNDERLINE;
        if (!is_integer($mode) || $mode < 0 || ($mode & $allModes) != $mode) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $this->connector->write(CPrinter_EscPos::ESC . '!' . chr($mode));

        return $this;
    }

    /**
     * Select user-defined character set.
     *
     * @param bool $on true to enable user-defined character set, false to use built-in characters sets
     *
     * @return $this
     */
    public function selectUserDefinedCharacterSet($on = true) {
        $this->connector->write(CPrinter_EscPos::ESC . '%' . ($on ? chr(1) : chr(0)));

        return $this;
    }

    /**
     * Set barcode height.
     *
     * @param int $height Height in dots. If not specified, 8 will be used.
     *
     * @return $this
     */
    public function setBarcodeHeight(int $height = 8) {
        self::validateInteger($height, 1, 255, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::GS . 'h' . chr($height));

        return $this;
    }

    /**
     * Set barcode bar width.
     *
     * @param int $width Bar width in dots. If not specified, 3 will be used.
     *                   Values above 6 appear to have no effect.
     *
     * @return $this
     */
    public function setBarcodeWidth(int $width = 3) {
        self::validateInteger($width, 1, 255, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::GS . 'w' . chr($width));

        return $this;
    }

    /**
     * Set the position for the Human Readable Interpretation (HRI) of barcode characters.
     *
     * @param int $position. Use Printer::BARCODE_TEXT_NONE to hide the text (default),
     *                       or any combination of Printer::BARCODE_TEXT_ABOVE and Printer::BARCODE_TEXT_BELOW
     *                       flags to display the text.
     *
     * @return $this
     */
    public function setBarcodeTextPosition(int $position = CPrinter_EscPos::BARCODE_TEXT_NONE) {
        self::validateInteger($position, 0, 3, __FUNCTION__, 'Barcode text position');
        $this->connector->write(CPrinter_EscPos::GS . 'H' . chr($position));

        return $this;
    }

    /**
     * Turn double-strike mode on/off.
     *
     * @param bool $on true for double strike, false for no double strike
     *
     * @return $this
     */
    public function setDoubleStrike(bool $on = true) {
        self::validateBoolean($on, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::ESC . 'G' . ($on ? chr(1) : chr(0)));

        return $this;
    }

    /**
     * Select print color on printers that support multiple colors.
     *
     * @param int $color Color to use. Must be either Printer::COLOR_1 (default), or Printer::COLOR_2.
     *
     * @return $this
     */
    public function setColor(int $color = CPrinter_EscPos::COLOR_1) {
        self::validateInteger($color, 0, 1, __FUNCTION__, 'Color');
        $this->connector->write(CPrinter_EscPos::ESC . 'r' . chr($color));

        return $this;
    }

    /**
     * Turn emphasized mode on/off.
     *
     * @param bool $on true for emphasis, false for no emphasis
     *
     * @return $this
     */
    public function setEmphasis(bool $on = true) {
        self::validateBoolean($on, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::ESC . 'E' . ($on ? chr(1) : chr(0)));

        return $this;
    }

    /**
     * Select font. Most printers have two fonts (Fonts A and B), and some have a third (Font C).
     *
     * @param int $font The font to use. Must be either Printer::FONT_A, Printer::FONT_B, or Printer::FONT_C.
     *
     * @return $this
     */
    public function setFont(int $font = CPrinter_EscPos::FONT_A) {
        self::validateInteger($font, 0, 2, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::ESC . 'M' . chr($font));

        return $this;
    }

    /**
     * Select justification.
     *
     * @param int $justification one of Printer::JUSTIFY_LEFT, Printer::JUSTIFY_CENTER, or Printer::JUSTIFY_RIGHT
     *
     * @return $this
     */
    public function setJustification(int $justification = CPrinter_EscPos::JUSTIFY_LEFT) {
        self::validateInteger($justification, 0, 2, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::ESC . 'a' . chr($justification));

        return $this;
    }

    /**
     * Set the height of the line.
     *
     * Some printers will allow you to overlap lines with a smaller line feed.
     *
     * @param null|int $height The height of each line, in dots. If not set, the printer
     *                         will reset to its default line spacing.
     *
     * @return $this
     */
    public function setLineSpacing(?int $height = null) {
        if ($height === null) {
            // Reset to default
            $this->connector->write(CPrinter_EscPos::ESC . '2'); // Revert to default line spacing

            return;
        }
        self::validateInteger($height, 1, 255, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::ESC . '3' . chr($height));

        return $this;
    }

    /**
     * Set print area left margin. Reset to default with Printer::initialize().
     *
     * @param int $margin the left margin to set on to the print area, in dots
     *
     * @return $this
     */
    public function setPrintLeftMargin(int $margin = 0) {
        self::validateInteger($margin, 0, 65535, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::GS . 'L' . self::intLowHigh($margin, 2));

        return $this;
    }

    /**
     * Set print area width. This can be used to add a right margin to the print area.
     * Reset to default with Printer::initialize().
     *
     * @param int $width the width of the page print area, in dots
     *
     * @return $this
     */
    public function setPrintWidth(int $width = 512) {
        self::validateInteger($width, 1, 65535, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::GS . 'W' . self::intLowHigh($width, 2));

        return $this;
    }

    /**
     * Attach a different print buffer to the printer. Buffers are responsible for handling text output to the printer.
     *
     * @param CPrinter_EscPos_Contract_PrintBufferInterface $buffer the buffer to use
     *
     * @throws InvalidArgumentException where the buffer is already attached to a different printer
     *
     * @return $this
     */
    public function setPrintBuffer(CPrinter_EscPos_Contract_PrintBufferInterface $buffer) {
        if ($buffer === $this->buffer) {
            return;
        }
        if ($buffer->getPrinter() != null) {
            throw new InvalidArgumentException('This buffer is already attached to a printer.');
        }
        if ($this->buffer !== null) {
            $this->buffer->setPrinter(null);
        }
        $this->buffer = $buffer;
        $this->buffer->setPrinter($this);

        return $this;
    }

    /**
     * Set black/white reverse mode on or off. In this mode, text is printed white on a black background.
     *
     * @param bool $on true to enable, false to disable
     *
     * @return $this
     */
    public function setReverseColors(bool $on = true) {
        self::validateBoolean($on, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::GS . 'B' . ($on ? chr(1) : chr(0)));

        return $this;
    }

    /**
     * Set the size of text, as a multiple of the normal size.
     *
     * @param int $widthMultiplier  Multiple of the regular height to use (range 1 - 8)
     * @param int $heightMultiplier Multiple of the regular height to use (range 1 - 8)
     *
     * @return $this
     */
    public function setTextSize(int $widthMultiplier, int $heightMultiplier) {
        self::validateInteger($widthMultiplier, 1, 8, __FUNCTION__);
        self::validateInteger($heightMultiplier, 1, 8, __FUNCTION__);
        $c = (2 << 3) * ($widthMultiplier - 1) + ($heightMultiplier - 1);
        $this->connector->write(CPrinter_EscPos::GS . '!' . chr($c));

        return $this;
    }

    /**
     * Set underline for printed text.
     *
     * @param int $underline Either true/false, or one of Printer::UNDERLINE_NONE, Printer::UNDERLINE_SINGLE or Printer::UNDERLINE_DOUBLE. Defaults to Printer::UNDERLINE_SINGLE.
     *
     * @return $this
     */
    public function setUnderline(int $underline = CPrinter_EscPos::UNDERLINE_SINGLE) {
        /* Set the underline */
        self::validateInteger($underline, 0, 2, __FUNCTION__);
        $this->connector->write(CPrinter_EscPos::ESC . '-' . chr($underline));

        return $this;
    }

    /**
     * Print each line upside-down (180 degrees rotated).
     *
     * @param bool $on true to enable, false to disable
     *
     * @return $this
     */
    public function setUpsideDown(bool $on = true) {
        $this->connector->write(CPrinter_EscPos::ESC . '{' . ($on ? chr(1) : chr(0)));

        return $this;
    }

    /**
     * Add text to the buffer.
     *
     * Text should either be followed by a line-break, or feed() should be called
     * after this to clear the print buffer.
     *
     * @param string $str Text to print, as UTF-8
     *
     * @return $this
     */
    public function text(string $str) {
        $this->buffer->writeText((string) $str);

        return $this;
    }

    /**
     * Add Chinese text to the buffer. This is a specific workaround for Zijang printers-
     * The printer will be switched to a two-byte mode and sent GBK-encoded text.
     *
     * Support for this will be merged into a print buffer.
     *
     * @param string $str Text to print, as UTF-8
     *
     * @return $this
     */
    public function textChinese(string $str = '') {
        $this->connector->write(CPrinter_EscPos::FS . '&');
        $str = \UConverter::transcode($str, 'GBK', 'UTF-8');
        $this->buffer->writeTextRaw((string) $str);
        $this->connector->write(CPrinter_EscPos::FS . '.');

        return $this;
    }

    /**
     * Add text to the buffer without attempting to interpret chararacter codes.
     *
     * Text should either be followed by a line-break, or feed() should be called
     * after this to clear the print buffer.
     *
     * @param string $str Text to print
     *
     * @return $this
     */
    public function textRaw(string $str = '') {
        $this->buffer->writeTextRaw((string) $str);

        return $this;
    }

    /**
     * Wrapper for GS ( k, to calculate and send correct data length.
     *
     * @param string $fn   Function to use
     * @param string $cn   Output code type. Affects available data
     * @param string $data data to send
     * @param string $m    Modifier/variant for function. Often '0' where used.
     *
     * @throws InvalidArgumentException where the input lengths are bad
     *
     * @return $this
     */
    protected function wrapperSend2dCodeData(string $fn, string $cn, string $data = '', string $m = '') {
        if (strlen($m) > 1 || strlen($cn) != 1 || strlen($fn) != 1) {
            throw new InvalidArgumentException('wrapperSend2dCodeData: cn and fn must be one character each.');
        }
        $header = $this->intLowHigh(strlen($data) + strlen($m) + 2, 2);
        $this->connector->write(CPrinter_EscPos::GS . '(k' . $header . $cn . $fn . $m . $data);

        return $this;
    }

    /**
     * Wrapper for GS ( L, to calculate and send correct data length.
     *
     * @param string $m    Modifier/variant for function. Usually '0'.
     * @param string $fn   function number to use, as character
     * @param string $data data to send
     *
     * @throws InvalidArgumentException where the input lengths are bad
     *
     * @return $this
     */
    protected function wrapperSendGraphicsData(string $m, string $fn, string $data = '') {
        if (strlen($m) != 1 || strlen($fn) != 1) {
            throw new InvalidArgumentException('wrapperSendGraphicsData: m and fn must be one character each.');
        }
        $header = $this->intLowHigh(strlen($data) + 2, 2);
        $this->connector->write(CPrinter_EscPos::GS . '(L' . $header . $m . $fn . $data);

        return $this;
    }

    /**
     * Convert widths and heights to characters. Used before sending graphics to set the size.
     *
     * @param array $inputs
     * @param bool  $long   True to use 4 bytes, false to use 2
     *
     * @return string
     */
    protected static function dataHeader(array $inputs, bool $long = true) {
        $outp = [];
        foreach ($inputs as $input) {
            if ($long) {
                $outp[] = CPrinter_EscPos_Printer::intLowHigh($input, 2);
            } else {
                self::validateInteger($input, 0, 255, __FUNCTION__);
                $outp[] = chr($input);
            }
        }

        return implode('', $outp);
    }

    /**
     * Generate two characters for a number: In lower and higher parts, or more parts as needed.
     *
     * @param int $input  Input number
     * @param int $length the number of bytes to output (1 - 4)
     */
    protected static function intLowHigh(int $input, int $length) {
        $maxInput = (256 << ($length * 8) - 1);
        self::validateInteger($length, 1, 4, __FUNCTION__);
        self::validateInteger($input, 0, $maxInput, __FUNCTION__);
        $outp = '';
        for ($i = 0; $i < $length; $i++) {
            $outp .= chr($input % 256);
            $input = (int) ($input / 256);
        }

        return $outp;
    }

    /**
     * Throw an exception if the argument given is not a boolean.
     *
     * @param bool   $test   the input to test
     * @param string $source the name of the function calling this
     */
    protected static function validateBoolean(bool $test, string $source) {
        if (!($test === true || $test === false)) {
            throw new InvalidArgumentException("Argument to $source must be a boolean");
        }
    }

    /**
     * Throw an exception if the argument given is not a float within the specified range.
     *
     * @param float  $test     the input to test
     * @param float  $min      the minimum allowable value (inclusive)
     * @param float  $max      the maximum allowable value (inclusive)
     * @param string $source   the name of the function calling this
     * @param string $argument the name of the invalid parameter
     */
    protected static function validateFloat(float $test, float $min, float $max, string $source, string $argument = 'Argument') {
        if (!is_numeric($test)) {
            throw new InvalidArgumentException("$argument given to $source must be a float, but '$test' was given.");
        }
        if ($test < $min || $test > $max) {
            throw new InvalidArgumentException("$argument given to $source must be in range $min to $max, but $test was given.");
        }
    }

    /**
     * Throw an exception if the argument given is not an integer within the specified range.
     *
     * @param int    $test     the input to test
     * @param int    $min      the minimum allowable value (inclusive)
     * @param int    $max      the maximum allowable value (inclusive)
     * @param string $source   the name of the function calling this
     * @param string $argument the name of the invalid parameter
     */
    protected static function validateInteger(int $test, int $min, int $max, string $source, string $argument = 'Argument') {
        self::validateIntegerMulti($test, [[$min, $max]], $source, $argument);
    }

    /**
     * Throw an exception if the argument given is not an integer within one of the specified ranges.
     *
     * @param int    $test     the input to test
     * @param array  $ranges   array of two-item min/max ranges
     * @param string $source   the name of the function calling this
     * @param string $argument the name of the invalid parameter
     */
    protected static function validateIntegerMulti(int $test, array $ranges, string $source, string $argument = 'Argument') {
        if (!is_integer($test)) {
            throw new InvalidArgumentException("$argument given to $source must be a number, but '$test' was given.");
        }
        $match = false;
        foreach ($ranges as $range) {
            $match |= $test >= $range[0] && $test <= $range[1];
        }
        if (!$match) {
            // Put together a good error "range 1-2 or 4-6"
            $rangeStr = 'range ';
            for ($i = 0; $i < count($ranges); $i++) {
                $rangeStr .= $ranges[$i][0] . '-' . $ranges[$i][1];
                if ($i == count($ranges) - 1) {
                    continue;
                } elseif ($i == count($ranges) - 2) {
                    $rangeStr .= ' or ';
                } else {
                    $rangeStr .= ', ';
                }
            }

            throw new InvalidArgumentException("$argument given to $source must be in $rangeStr, but $test was given.");
        }
    }

    /**
     * Throw an exception if the argument doesn't match the given regex.
     *
     * @param string $test     the input to test
     * @param string $source   the name of the function calling this
     * @param string $regex    valid values for this attribute, as a regex
     * @param string $argument the name of the parameter being validated
     *
     * @throws InvalidArgumentException Where the argument is not valid
     */
    protected static function validateStringRegex(string $test, string $source, string $regex, string $argument = 'Argument') {
        if (preg_match($regex, $test) === 0) {
            throw new InvalidArgumentException("$argument given to $source is invalid. It should match regex '$regex', but '$test' was given.");
        }
    }
}
