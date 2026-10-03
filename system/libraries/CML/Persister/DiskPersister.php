<?php

use Rubix\ML\Encoding;
use Rubix\ML\Persisters\Persister;
use Rubix\ML\Exceptions\RuntimeException;

/**
 * Persister model Rubix lewat disk CF (CStorage) - lokal, S3, SFTP, atau FTP, mengikuti
 * konfigurasi disk di default/config/storage.php app. Berguna untuk model yang harus hidup
 * di luar filesystem lokal server (mis. lintas proses/daemon, atau server tanpa disk persisten).
 *
 * $path adalah key relatif terhadap disk (bukan path lokal) - jangan diawali DOCROOT/absolute
 * path seperti CML_Manager::getDefaultModelPath(), itu cuma masuk akal untuk Filesystem bawaan
 * Rubix.
 */
class CML_Persister_DiskPersister implements Persister {
    /**
     * @var string
     */
    protected $disk;

    /**
     * @var string
     */
    protected $path;

    /**
     * @param string $disk nama disk terdaftar (lihat default/config/storage.php)
     * @param string $path key relatif di disk itu
     */
    public function __construct($disk, $path) {
        $this->disk = $disk;
        $this->path = $path;
    }

    /**
     * @param Encoding $encoding
     *
     * @throws RuntimeException
     */
    public function save(Encoding $encoding): void {
        if ($encoding->bytes() === 0) {
            throw new RuntimeException('Encoding does not contain any data.');
        }

        $success = c::storage()->disk($this->disk)->put($this->path, $encoding->data());

        if (!$success) {
            throw new RuntimeException("Could not write to disk '{$this->disk}'.");
        }
    }

    /**
     * @throws RuntimeException
     *
     * @return Encoding
     */
    public function load(): Encoding {
        if (!c::storage()->disk($this->disk)->exists($this->path)) {
            throw new RuntimeException("File {$this->path} does not exist on disk '{$this->disk}'.");
        }

        $data = c::storage()->disk($this->disk)->get($this->path);

        $encoding = new Encoding($data);

        if ($encoding->bytes() === 0) {
            throw new RuntimeException('File does not contain any data.');
        }

        return $encoding;
    }

    /**
     * @internal
     *
     * @return string
     */
    public function __toString(): string {
        return "Disk (disk: {$this->disk}, path: {$this->path})";
    }
}
