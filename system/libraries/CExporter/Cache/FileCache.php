<?php

use Psr\SimpleCache\CacheInterface;

/**
 * Implementasi PSR-16 berbasis satu berkas di disk (bukan array di memori)
 * untuk Settings::setCache() milik PhpSpreadsheet, dipakai lewat
 * CExporter::runWithDiskCellCache() untuk export dengan baris sangat banyak.
 *
 * Berkas append-only: set() menambahkan [4 byte panjang][data serialize()] di
 * akhir, baris lama yang di-set ulang meninggalkan sisa yatim (diterima untuk
 * pemakaian sekali-pakai seperti export). Indeks kunci->offset tetap di
 * memori, jauh lebih kecil daripada menahan objek Cell itu sendiri.
 */
class CExporter_Cache_FileCache implements CacheInterface {
    /**
     * @var resource
     */
    protected $handle;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var int[] kunci => offset byte awal blok di $filePath
     */
    protected $index = [];

    /**
     * @param null|string $filePath kosongkan untuk berkas temp baru otomatis dihapus saat selesai
     */
    public function __construct($filePath = null) {
        $this->filePath = $filePath ?: tempnam(sys_get_temp_dir(), 'cexporter_cellcache_');
        $handle = fopen($this->filePath, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Tidak bisa membuka berkas cache: ' . $this->filePath);
        }
        $this->handle = $handle;
    }

    public function __destruct() {
        $this->close();
    }

    /**
     * Tutup handle dan hapus berkas temp - dipanggil otomatis di destructor,
     * tapi panggil manual sesegera mungkin setelah export selesai supaya
     * tidak menunggu garbage collector untuk melepas berkasnya.
     *
     * @return void
     */
    public function close() {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
        $this->index = [];
    }

    /**
     * @return bool
     */
    public function clear() {
        $this->index = [];
        ftruncate($this->handle, 0);

        return true;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete($key) {
        unset($this->index[$key]);

        return true;
    }

    /**
     * @param iterable $keys
     *
     * @return bool
     */
    public function deleteMultiple($keys) {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null) {
        if (!$this->has($key)) {
            return $default;
        }
        fseek($this->handle, $this->index[$key]);
        $lengthPacked = fread($this->handle, 4);
        $length = unpack('N', $lengthPacked)[1];
        $data = $length > 0 ? fread($this->handle, $length) : '';

        return unserialize($data);
    }

    /**
     * @param iterable $keys
     * @param mixed    $default
     *
     * @return iterable
     */
    public function getMultiple($keys, $default = null) {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key) {
        return array_key_exists($key, $this->index);
    }

    /**
     * @param string                 $key
     * @param mixed                  $value
     * @param null|DateInterval|int $ttl TTL diabaikan - cache ini seumur hidup satu proses export
     *
     * @return bool
     */
    public function set($key, $value, $ttl = null) {
        $data = serialize($value);
        fseek($this->handle, 0, SEEK_END);
        $offset = ftell($this->handle);
        fwrite($this->handle, pack('N', strlen($data)) . $data);
        $this->index[$key] = $offset;

        return true;
    }

    /**
     * @param iterable               $values
     * @param null|DateInterval|int $ttl
     *
     * @return bool
     */
    public function setMultiple($values, $ttl = null) {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return true;
    }
}
