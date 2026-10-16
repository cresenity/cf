<?php

class CLogger_Rotator_FilenameFormat {
    /**
     * Path to files to rotate / delete.
     *
     * @var string
     */
    protected $path;

    /**
     * Filename pattern of files to rotate / delete.
     *
     * @var string
     */
    protected $filenamePattern;

    /**
     * Filename regex pattern to match files.
     *
     * @var string
     */
    protected $filenameRegex;

    /**
     * Date format within filename.
     *
     * @var string
     */
    protected $dateFormat;

    /**
     * Constructor.
     *
     * @param string $filenameFormat Filename format to match files against
     *
     * @throws CLogger_Rotator_Exception_FilenameFormatException
     */
    public function __construct($filenameFormat) {
        $this->path = dirname($filenameFormat);
        if (!is_dir($this->path) || !is_readable($this->path)) {
            throw new CLogger_Rotator_Exception_FilenameFormatException('Directory path does not exist or is not readable at: ' . strip_tags($filenameFormat));
        }
        $this->filenamePattern = basename($filenameFormat);
        $this->filenameRegex = $this->extractRegex($this->filenamePattern);
    }

    /**
     * Extract regex pattern from filename pattern.
     *
     * * matches any string, for example *.log matches all files ending .log
     * {Ymd} = matches time segment in a file, for example order.{Ymd}.log matches a file in the format order.20160401.log
     * Any date format supported by DateTime::createFromFormat is allowed (excluding the Timezone identifier 'e' and whitespace; separators - and _ are allowed)
     * Only the first valid {..} is the date; any other {..} and the rest of the name are matched literally
     *
     * @param string $filename
     *
     * @throws CLogger_Rotator_Exception_FilenameFormatException
     *
     * @return string Regex pattern for matching files (with the ungreedy modifier set)
     */
    public function extractRegex($filename) {
        if (strpos($filename, '/') !== false) {
            throw new CLogger_Rotator_Exception_FilenameFormatException("Filename part cannot contain '/' character");
        }
        // bagian literal di-preg_quote, nama log CCron/CDaemon bisa berisi ( ) [ ] dll
        $pattern = '';
        $dateFound = false;
        $parts = preg_split('/(\{[^}]+\}|\*)/', $filename, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            if ($part === '*') {
                $pattern .= '.+';

                continue;
            }
            if (!$dateFound && preg_match('/^\{([^}]+)\}$/', $part, $m) && $this->isValidDateFormat($m[1])) {
                $this->dateFormat = $m[1];
                $dateFound = true;
                $pattern .= '([^.]+)';

                continue;
            }
            $pattern .= preg_quote($part, '/');
        }

        return '/^' . $pattern . '$/';
    }

    /**
     * Karakter format DateTime::createFromFormat, ditambah pemisah - dan _.
     *
     * @param string $dateFormat
     *
     * @return bool
     */
    protected function isValidDateFormat($dateFormat) {
        return empty(array_diff(str_split($dateFormat), str_split('djDlSzFMmnYyaAghGHisuOPTU-_')));
    }

    /**
     * Return path to folder we're looking for files in.
     *
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * Return filename pattern to match files.
     *
     * @return string
     */
    public function getFilenamePattern() {
        return $this->filenamePattern;
    }

    /**
     * Return regex to match files.
     *
     * @return string
     */
    public function getFilenameRegex() {
        return $this->filenameRegex;
    }

    /**
     * Does the filename pattern contain a date format?
     *
     * @return bool
     */
    public function hasDateFormat() {
        return $this->dateFormat !== null;
    }

    /**
     * Return the date format.
     *
     * @return string
     */
    public function getDateFormat() {
        return $this->dateFormat;
    }
}
