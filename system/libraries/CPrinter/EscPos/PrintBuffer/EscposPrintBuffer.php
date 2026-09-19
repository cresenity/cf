<?php

/**
 * This class manages newlines and character encoding for the target printer, and
 * can be interchanged for an image-bassed buffer (ImagePrintBuffer) if you can't
 * get it operating properly on your machine.
 */
class CPrinter_EscPos_PrintBuffer_EscposPrintBuffer implements CPrinter_EscPos_Contract_PrintBufferInterface {
    /**
     *  True to cache output as .z, false to leave un-compressed (useful for debugging).
     */
    const COMPRESS_CACHE = true;

    /**
     * Un-recognised characters will be replaced with this.
     */
    const REPLACEMENT_CHAR = '?';

    /**
     * @var array
     *            Map code points to printer-specific code page numbers which contain them
     */
    private $available = null;

    /**
     * @var array
     *            Map code pages to a map of code points to encoding-specific characters 128-255
     */
    private $encode = null;

    /**
     * @var null|Printer
     *                   Printer for output
     */
    private $printer;

    /**
     * Empty print buffer.
     */
    public function __construct() {
        $this->printer = null;
    }

    public function flush() {
        if ($this->printer == null) {
            throw new LogicException('Not attached to a printer.');
        }
        // TODO Not yet implemented for this buffer: This indicates that the printer needs the current line to be ended.
    }

    public function getPrinter() {
        return $this->printer;
    }

    public function setPrinter(?CPrinter_EscPos_Printer $printer = null) {
        $this->printer = $printer;
        if ($printer != null) {
            $this->loadAvailableCharacters();
        }
    }

    public function writeText(string $text) {
        if ($this->printer == null) {
            throw new LogicException('Not attached to a printer.');
        }
        // Normalize text - this replaces combining characters with composed glyphs, and also helps us eliminated bad UTF-8 early
        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text);
        } elseif (!mb_check_encoding($text, 'UTF-8')) {
            $text = false;
        }
        if ($text === false) {
            throw new \Exception('Input must be UTF-8');
        }
        // Iterate code points
        $encoding = $this->printer->getCharacterTable();
        $currentBlock = [];
        foreach ($this->codePoints($text) as $codePoint) {
            // See if we need to change code pages
            $matching = !isset($this->available[$codePoint]) || isset($this->encode[$encoding][$codePoint]);
            if ($matching) {
                $currentBlock[] = $codePoint;
            } else {
                // Write what we have
                $this->writeTextUsingEncoding($currentBlock, $encoding);
                // New encoding..
                $encoding = self::identifyText($codePoint);
                $currentBlock = [$codePoint];
            }
        }
        // Write out last bytes
        if (count($currentBlock) != 0) {
            $this->writeTextUsingEncoding($currentBlock, $encoding);
        }
    }

    /**
     * Code point tiap karakter; tanpa ekstensi intl memakai mb_ord.
     *
     * @param string $text
     *
     * @return int[]
     */
    private function codePoints(string $text) {
        $codePoints = [];
        if (class_exists('IntlBreakIterator')) {
            $codePointIterator = \IntlBreakIterator::createCodePointInstance();
            $codePointIterator->setText($text);
            while ($codePointIterator->next() > 0) {
                $codePoints[] = $codePointIterator->getLastCodePoint();
            }

            return $codePoints;
        }
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $codePoints[] = mb_ord($char, 'UTF-8');
        }

        return $codePoints;
    }

    public function writeTextRaw(string $text) {
        if ($this->printer == null) {
            throw new LogicException('Not attached to a printer.');
        }
        if (strlen($text) == 0) {
            return;
        }
        // Pass only printable characters
        $j = 0;
        $l = strlen($text);
        $outp = str_repeat(self::REPLACEMENT_CHAR, $l);
        for ($i = 0; $i < $l; $i++) {
            $c = substr($text, $i, 1);
            if ($c == "\r") {
                /* Skip past Windows line endings (raw usage). */
                continue;
            } elseif (self::asciiCheck($c, true)) {
                $outp[$j] = $c;
            }
            $j++;
        }
        $this->write(substr($outp, 0, $j));
    }

    /**
     * Return an encoding which we can start to use for outputting this text.
     * Later parts of the text need not be included in the returned code page.
     *
     * @param int $codePoint code point to check
     *
     * @return bool|int code page number, or FALSE if the text is not
     *                  printable on any supported encoding
     */
    private function identifyText(int $codePoint) {
        if (!isset($this->available[$codePoint])) {
            /* Character not available anywhere */
            return false;
        }

        return $this->available[$codePoint];
    }

    /**
     * Based on the printer's connector, compute (or load a cached copy of) maps
     * of UTF character to unicode characters for later use.
     */
    private function loadAvailableCharacters() {
        $profile = $this->printer->getPrinterCapabilityProfile();
        $supportedCodePages = $profile->getCodePages();
        $profileName = $profile->getId();
        $cacheFile = dirname(__FILE__) . '/cache/Characters-' . $profileName . '.ser'
            . (self::COMPRESS_CACHE ? '.z' : '');
        $cacheKey = $profile->getCodePageCacheKey();
        /* Check for pre-generated file */
        if (file_exists($cacheFile)) {
            $cacheData = file_get_contents($cacheFile);
            if (self::COMPRESS_CACHE) {
                $cacheData = gzuncompress($cacheData);
            }
            if ($cacheData) {
                $dataArray = unserialize($cacheData);
                if (isset($dataArray['key'], $dataArray['available'], $dataArray['encode'])
                    && $dataArray['key'] == $cacheKey
                ) {
                    $this->available = $dataArray['available'];
                    $this->encode = $dataArray['encode'];

                    return;
                }
            }
        }

        /* Generate conversion tables */
        $encodeLegacy = [];
        $encode = [];
        $available = [];

        foreach ($supportedCodePages as $num => $codePage) {
            $encodeLegacy[$num] = [];
            if (!$codePage->isEncodable()) {
                continue;
            }
            $map = $codePage->getDataArray();
            $encodeMap = [];
            for ($char = 128; $char <= 255; $char++) {
                $codePoint = $map[$char - 128];
                if ($codePoint == CPrinter_EscPos_CodePage::MISSING_CHAR_CODE) { // Skip placeholders
                    continue;
                }
                $encodeMap[$codePoint] = $char;
                if (!isset($available[$codePoint])) {
                    $available[$codePoint] = $num;
                }
            }
            $encode[$num] = $encodeMap;
        }

        /* Use generated data */
        $dataArray = [
            'available' => $available,
            'encode' => $encode,
            'key' => $cacheKey
        ];
        $this->available = $available;
        $this->encode = $encode;

        $cacheData = serialize($dataArray);
        if (self::COMPRESS_CACHE) {
            $cacheData = gzcompress($cacheData);
        }
        /* Attempt to cache, but don't worry if we can't */
        @file_put_contents($cacheFile, $cacheData);
    }

    /**
     * Encode a block of text using the specified map, and write it to the printer.
     *
     * @param array $codePoints Text to print, as list of unicode code points
     * @param int   $encodingNo encoding number to use- assumed to exist
     */
    private function writeTextUsingEncoding(array $codePoints, int $encodingNo) {
        $encodeMap = $this->encode[$encodingNo];
        $len = count($codePoints);

        $rawText = str_repeat(self::REPLACEMENT_CHAR, $len);
        $bytesWritten = 0;
        $cr = 0x0D; // extra character from line endings on Windows
        for ($i = 0; $i < $len; $i++) {
            $codePoint = $codePoints[$i];
            if (isset($encodeMap[$codePoint])) {
                // Printable via selected code page
                $rawText[$bytesWritten] = chr($encodeMap[$codePoint]);
            } elseif (($codePoint > 31 && $codePoint < 127) || $codePoint == 10) {
                // Printable as ASCII
                $rawText[$bytesWritten] = chr($codePoint);
            } elseif ($codePoint === $cr) {
                // Skip past Windows line endings, LF is fine
                continue;
            }
            $bytesWritten++;
        }
        if ($this->printer->getCharacterTable() != $encodingNo) {
            $this->printer->selectCharacterTable($encodingNo);
        }
        $this->writeTextRaw(substr($rawText, 0, $bytesWritten));
    }

    /**
     * Write data to the underlying printer.
     *
     * @param string $data
     */
    private function write(string $data) {
        $this->printer->getPrintConnector()->write($data);
    }

    /**
     * Return true if a character is an ASCII printable character.
     *
     * @param string $char     Character to check
     * @param bool   $extended True to allow 128-256 values also (excluded by default)
     *
     * @return bool true if the character is printable, false if it is not
     */
    private static function asciiCheck(string $char, bool $extended = false) {
        if (strlen($char) != 1) {
            // Multi-byte string
            return false;
        }
        $num = ord($char);
        if ($num > 31 && $num < 127) { // Printable
            return true;
        }
        if ($num == 10) { // New-line (printer will take these)
            return true;
        }
        if ($extended && $num > 127) {
            return true;
        }

        return false;
    }
}
