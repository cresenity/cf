<?php

/**
 * Description of FileSessionHandler.
 */

use Symfony\Component\Finder\Finder;

class CSession_Handler_FileSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    /**
     * The filesystem instance.
     *
     * @var \CFile
     */
    protected $files;

    /**
     * The path where sessions should be stored.
     *
     * @var string
     */
    protected $path;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $seconds;

    /**
     * Create a new file driven handler instance.
     *
     * @param string $path
     * @param int    $seconds
     *
     * @return void
     */
    public function __construct($path, $seconds) {
        $this->files = new CFile();
        $this->path = DOCROOT . 'temp' . DS . 'session';
        if (!is_dir($this->path)) {
            CFile::makeDirectory($this->path, 0755, true);
        }
        $this->seconds = $seconds;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function read($sessionId) {
        if ($this->files->isFile($path = $this->path . '/' . $sessionId)) {
            if ($this->files->lastModified($path) >= CCarbon::now()->subSeconds($this->seconds)->getTimestamp()) {
                return $this->files->sharedGet($path);
            }
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        $this->files->put($this->path . '/' . $sessionId, $data, true);

        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        $this->files->delete($this->path . '/' . $sessionId);

        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function gc($lifetime) {
        $files = Finder::create()
            ->in($this->path)
            ->files()
            ->ignoreDotFiles(true)
            ->date('<= now - ' . $lifetime . ' seconds');

        foreach ($files as $file) {
            $this->files->delete($file->getRealPath());
        }
    }

    /**
     * Benar bila id sesi ini ada dan belum kedaluwarsa di penyimpanan.
     *
     * @param string $sessionId
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function validateId($sessionId) {
        $path = $this->path . '/' . $sessionId;

        return $this->files->isFile($path) && $this->files->lastModified($path) >= CCarbon::now()->subSeconds($this->seconds)->getTimestamp();
    }

    /**
     * Perbarui waktu akses sesi tanpa mengubah datanya.
     *
     * @param string $sessionId
     * @param string $data
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function updateTimestamp($sessionId, $data) {
        $path = $this->path . '/' . $sessionId;
        if ($this->files->isFile($path)) {
            @touch($path);
        }

        return true;
    }
}
