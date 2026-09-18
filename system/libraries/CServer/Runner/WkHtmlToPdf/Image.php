<?php

use CRunner_WkHtmlToPdf_TmpFile as File;
use CRunner_WkHtmlToPdf_Command as Command;

/**
 * Pdf.
 *
 * This class is a slim wrapper around `wkhtmltoimage`.
 */
class CServer_Runner_WkHtmlToPdf_Image {
    // Regular expression to detect HTML strings
    const REGEX_HTML = '/<html/i';

    // prefix for tmp files
    const TMP_PREFIX = 'tmp_wkhtmlto_pdf_';

    /**
     * @var string the name of the `wkhtmltoimage` binary. Default is
     *             `wkhtmltoimage`. You can also configure a full path here.
     */
    public $binary = 'wkhtmltoimage';

    /**
     * @var string the image type. Default is 'png'. Other options are 'jpg'
     *             and 'bmp'.
     */
    public $type = 'png';

    /**
     * @var array options to pass to the Command constructor. Default is none.
     */
    public $commandOptions = [];

    /**
     * @var null|string the directory to use for temporary files. If `null`
     *                  (default) the dir is autodetected.
     */
    public $tmpDir;

    /**
     * @var bool whether to ignore any errors if some PDF file was still
     *           created. Default is `false`.
     */
    public $ignoreWarnings = false;

    /**
     * @var bool whether the PDF was created
     */
    protected $isCreated = false;

    /**
     * @var \CRunner_WkHtmlToPdf_TmpFile|string the page input or a `File` instance for
     *                                          HTML string inputs
     */
    protected $page;

    /**
     * @var array options for `wkhtmltoimage` as `['--opt1', '--opt2' => 'val',
     *            ...]`
     */
    protected $options = [];

    /**
     * @var CRunner_WkHtmlToPdf_TmpFile the temporary image file
     */
    protected $tmpImageFile;

    /**
     * @var CRunner_WkHtmlToPdf_Command the command instance that executes wkhtmltopdf
     */
    protected $command;

    /**
     * @var string the detailed error message. Empty string if none.
     */
    protected $error = '';

    /**
     * @param array|string $options global options for wkhtmltoimage, a page
     *                              URL, a HTML string or a filename
     */
    public function __construct($options = null) {
        if (is_array($options)) {
            $this->setOptions($options);
        } elseif (is_string($options)) {
            $this->setPage($options);
        }
    }

    /**
     * Add a page object to the output.
     *
     * @param string $page either a URL, a HTML string or a filename
     *
     * @return static the Image instance for method chaining
     */
    public function setPage($page) {
        $this->page = preg_match(self::REGEX_HTML, $page) ? new File($page, '.html') : $page;

        return $this;
    }

    /**
     * Save the image to given filename (triggers image creation).
     *
     * @param string $filename to save image as
     *
     * @return bool whether image was created successfully
     */
    public function saveAs($filename) {
        if (!$this->isCreated && !$this->createImage()) {
            return false;
        }
        if (!$this->tmpImageFile->saveAs($filename)) {
            $tmpFile = $this->tmpImageFile->getFileName();
            $this->error = "Could not copy image from tmp location '$tmpFile' to '$filename'";

            return false;
        }

        return true;
    }

    public function getTempFilename() {
        if (!$this->isCreated && !$this->createImage()) {
            return false;
        }

        return $this->tmpImageFile->getFileName();
    }

    /**
     * Send image to client, either inline or as download (triggers image
     * creation).
     *
     * @param null|string $filename the filename to send. If empty, the PDF is
     *                              streamed inline. Note, that the file extension must match what you
     *                              configured as $type (png, jpg, ...).
     * @param bool        $inline   whether to force inline display of the image, even
     *                              if filename is present
     *
     * @return bool whether image was created successfully
     */
    public function send($filename = null, $inline = false) {
        if (!$this->isCreated && !$this->createImage()) {
            return false;
        }
        $this->tmpImageFile->send($filename, $this->getMimeType(), $inline);

        return true;
    }

    /**
     * Get the raw Image contents (triggers Image creation).
     *
     * @return string|bool the Image content as a string or `false` if the
     *                     Image wasn't created successfully
     */
    public function toString() {
        if (!$this->isCreated && !$this->createImage()) {
            return false;
        }

        return file_get_contents($this->tmpImageFile->getFileName());
    }

    public function getTmpFilename() {
        if (!$this->isCreated && !$this->createImage()) {
            return false;
        }

        return $this->tmpImageFile->getFileName();
    }

    /**
     * Set options.
     *
     * @param array $options list of image options to set as name/value pairs
     *
     * @return static the Image instance for method chaining
     */
    public function setOptions($options = []) {
        foreach ($options as $key => $val) {
            if (is_int($key)) {
                $this->options[] = $val;
            } elseif ($key[0] !== '_' && property_exists($this, $key)) {
                $this->$key = $val;
            } else {
                $this->options[$key] = $val;
            }
        }

        return $this;
    }

    /**
     * @return Command the command instance that executes wkhtmltopdf
     */
    public function getCommand() {
        if ($this->command === null) {
            $options = $this->commandOptions;
            if (!isset($options['command'])) {
                $options['command'] = $this->binary;
            }
            $this->command = new Command($options);
        }

        return $this->command;
    }

    /**
     * @return string the detailed error message. Empty string if none.
     */
    public function getError() {
        return $this->error;
    }

    /**
     * @return string the filename of the temporary image file
     */
    public function getImageFilename() {
        if ($this->tmpImageFile === null) {
            $this->tmpImageFile = new File('', '.' . $this->type, self::TMP_PREFIX);
        }

        return $this->tmpImageFile->getFileName();
    }

    /**
     * @throws \Exception
     *
     * @return string the mime type for the current image
     */
    public function getMimeType() {
        if ($this->type === 'jpg') {
            return 'image/jpeg';
        } elseif ($this->type === 'png') {
            return 'image/png';
        } elseif ($this->type === 'bmp') {
            return 'image/bmp';
        } else {
            throw new \Exception('Invalid image type');
        }
    }

    /**
     * Run the Command to create the tmp image file.
     *
     * @return bool whether creation was successful
     */
    protected function createImage() {
        if ($this->isCreated) {
            return false;
        }

        $command = $this->getCommand();
        $fileName = $this->getImageFilename();

        $command->addArgs($this->options);
        // Always escape input and output filename
        $command->addArg((string) $this->page, null, true);
        $command->addArg($fileName, null, true);
        if (!$command->execute()) {
            $this->error = $command->getError();
            if (!(file_exists($fileName) && filesize($fileName) !== 0 && $this->ignoreWarnings)) {
                return false;
            }
        }
        $this->isCreated = true;

        return true;
    }

    public function getExecCommand() {
        return $this->getCommand()->getExecCommand();
    }
}
