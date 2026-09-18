<?php

/**
 * Description of File.
 *
 * @author Hery
 */
class CHTTP_Testing_File extends CHTTP_UploadedFile {
    /**
     * The name of the file.
     *
     * @var string
     */
    public $name;

    /**
     * The temporary file resource.
     *
     * @var resource
     */
    public $tempFile;

    /**
     * The "size" to report.
     *
     * @var int
     */
    public $sizeToReport;

    /**
     * The MIME type to report.
     *
     * @var null|string
     */
    public $mimeTypeToReport;

    /**
     * Create a new file instance.
     *
     * @param string   $name
     * @param resource $tempFile
     *
     * @return void
     */
    public function __construct($name, $tempFile) {
        $this->name = $name;
        $this->tempFile = $tempFile;

        // vendored UploadedFile takes ($path, $originalName, $mimeType, $size, $error, $test)
        parent::__construct(
            $this->tempFilePath(),
            $name,
            $this->getMimeType(),
            null,
            null,
            true
        );
    }

    /**
     * Create a new fake file.
     *
     * @param string     $name
     * @param string|int $kilobytes
     *
     * @return CHTTP_Testing_File
     */
    public static function create($name, $kilobytes = 0) {
        return (new CHTTP_Testing_FileFactory())->create($name, $kilobytes);
    }

    /**
     * Create a new fake file with content.
     *
     * @param string $name
     * @param string $content
     *
     * @return CHTTP_Testing_File
     */
    public static function createWithContent($name, $content) {
        return (new CHTTP_Testing_FileFactory())->createWithContent($name, $content);
    }

    /**
     * Create a new fake image.
     *
     * @param string $name
     * @param int    $width
     * @param int    $height
     *
     * @return CHTTP_Testing_File
     */
    public static function image($name, $width = 10, $height = 10) {
        return (new CHTTP_Testing_FileFactory())->image($name, $width, $height);
    }

    /**
     * Set the "size" of the file in kilobytes.
     *
     * @param int $kilobytes
     *
     * @return $this
     */
    public function size($kilobytes) {
        $this->sizeToReport = $kilobytes * 1024;

        return $this;
    }

    /**
     * Get the size of the file.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function getSize() {
        return $this->sizeToReport ?: parent::getSize();
    }

    /**
     * Set the "MIME type" for the file.
     *
     * @param string $mimeType
     *
     * @return $this
     */
    public function mimeType($mimeType) {
        $this->mimeTypeToReport = $mimeType;

        return $this;
    }

    /**
     * Get the MIME type of the file.
     *
     * @return string
     */
    public function getMimeType() {
        return $this->mimeTypeToReport ?: CHTTP_Testing_MimeType::from($this->name);
    }

    /**
     * Get the path to the temporary file.
     *
     * @return string
     */
    protected function tempFilePath() {
        return stream_get_meta_data($this->tempFile)['uri'];
    }
}
