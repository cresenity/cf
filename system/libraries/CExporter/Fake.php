<?php

use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pengganti CExporter/CImporter untuk test: mencatat ekspor yang diunduh, disimpan, diantre, dan diekspor mentah,
 * serta impor, tanpa menulis atau membaca berkas apa pun.
 */
class CExporter_Fake {
    /**
     * @var array nama berkas => export
     */
    protected $downloads = [];

    /**
     * @var array disk => path => export
     */
    protected $stored = [];

    /**
     * @var array disk => path => export
     */
    protected $queued = [];

    /**
     * @var array disk => path => CExporter_Fake_PendingDispatch
     */
    protected $queuedDispatches = [];

    /**
     * @var array kelas export => export
     */
    protected $raws = [];

    /**
     * @var array disk => path => import
     */
    protected $imported = [];

    /**
     * @var bool
     */
    protected $matchByRegex = false;

    /**
     * Nama berkas pada assertion dicocokkan sebagai regex.
     *
     * @return $this
     */
    public function matchByRegex() {
        $this->matchByRegex = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function doesntMatchByRegex() {
        $this->matchByRegex = false;

        return $this;
    }

    // ---- pengganti CExporter ----

    /**
     * @param object      $export
     * @param string      $fileName
     * @param null|string $writerType
     * @param array       $headers
     *
     * @return BinaryFileResponse
     */
    public function download($export, $fileName, $writerType = null, array $headers = []) {
        $this->downloads[$fileName] = $export;

        return new BinaryFileResponse(__FILE__);
    }

    /**
     * @param object      $export
     * @param string      $filePath
     * @param null|string $writerType
     * @param array       $headers
     *
     * @return void
     */
    public function forceDownload($export, $filePath, $writerType = null, array $headers = []) {
        $this->downloads[$filePath] = $export;
    }

    /**
     * @param object $export
     * @param string $filePath
     * @param array  $options  diskName, writerType, queued, diskOptions
     *
     * @return bool|CExporter_Fake_PendingDispatch
     */
    public function store($export, $filePath, $options = []) {
        $diskName = carr::get($options, 'diskName');
        if (carr::get($options, 'queued', false)) {
            return $this->queue($export, $filePath, $diskName, carr::get($options, 'writerType'));
        }

        $this->stored[$diskName ?: 'default'][$filePath] = $export;

        return true;
    }

    /**
     * @param object      $export
     * @param string      $filePath
     * @param null|string $disk
     * @param null|string $writerType
     * @param mixed       $diskOptions
     *
     * @return CExporter_Fake_PendingDispatch
     */
    public function queue($export, $filePath, $disk = null, $writerType = null, $diskOptions = []) {
        $disk = $disk ?: 'default';
        $this->stored[$disk][$filePath] = $export;
        $this->queued[$disk][$filePath] = $export;

        return $this->queuedDispatches[$disk][$filePath] = new CExporter_Fake_PendingDispatch();
    }

    /**
     * @param string      $ajaxMethod
     * @param string      $filePath
     * @param null|string $disk
     * @param null|string $writerType
     * @param mixed       $diskOptions
     *
     * @return CExporter_Fake_PendingDispatch
     */
    public function queueAjax($ajaxMethod, $filePath, $disk = null, $writerType = null, $diskOptions = []) {
        return $this->queue($ajaxMethod, $filePath, $disk, $writerType, $diskOptions);
    }

    /**
     * @param object $export
     * @param string $writerType
     *
     * @return string
     */
    public function raw($export, $writerType) {
        $this->raws[get_class($export)] = $export;

        return 'RAW-CONTENTS';
    }

    // ---- pengganti CImporter ----

    /**
     * @param object      $import
     * @param mixed       $filePath
     * @param null|string $disk
     * @param null|string $readerType
     *
     * @return $this|CExporter_Fake_PendingDispatch
     */
    public function import($import, $filePath, $disk = null, $readerType = null) {
        if ($import instanceof CQueue_ShouldQueueInterface) {
            return $this->queueImport($import, $filePath, $disk, $readerType);
        }

        $this->recordImport($import, $filePath, $disk);

        return $this;
    }

    /**
     * @param object      $import
     * @param mixed       $filePath
     * @param null|string $disk
     * @param null|string $readerType
     *
     * @return CExporter_Fake_PendingDispatch
     */
    public function queueImport($import, $filePath, $disk = null, $readerType = null) {
        $this->recordImport($import, $filePath, $disk);

        return new CExporter_Fake_PendingDispatch();
    }

    /**
     * @param object      $import
     * @param mixed       $filePath
     * @param null|string $disk
     * @param null|string $readerType
     *
     * @return array
     */
    public function toArray($import, $filePath, $disk = null, $readerType = null) {
        $this->recordImport($import, $filePath, $disk);

        return [];
    }

    /**
     * @param object      $import
     * @param mixed       $filePath
     * @param null|string $disk
     * @param null|string $readerType
     *
     * @return CCollection
     */
    public function toCollection($import, $filePath, $disk = null, $readerType = null) {
        $this->recordImport($import, $filePath, $disk);

        return c::collect();
    }

    /**
     * @param object      $import
     * @param mixed       $filePath
     * @param null|string $disk
     *
     * @return void
     */
    protected function recordImport($import, $filePath, $disk) {
        if (is_object($filePath) && method_exists($filePath, 'getClientOriginalName')) {
            $filePath = $filePath->getClientOriginalName();
        }

        $this->imported[$disk ?: 'default'][$filePath] = $import;
    }

    // ---- assertion ----

    /**
     * @param string        $fileName
     * @param null|callable $callback menerima export, true berarti cocok
     *
     * @return void
     */
    public function assertDownloaded($fileName, $callback = null) {
        $name = $this->resolve($this->downloads, $fileName);

        PHPUnit::assertNotNull($name, sprintf('%s is not downloaded', $fileName));

        $this->assertCallback($callback, $this->downloads[$name], "The file [{$fileName}] was not downloaded with the expected data.");
    }

    /**
     * @param string $fileName
     *
     * @return void
     */
    public function assertNotDownloaded($fileName) {
        PHPUnit::assertNull($this->resolve($this->downloads, $fileName), sprintf('%s was downloaded unexpectedly', $fileName));
    }

    /**
     * @param string        $filePath
     * @param null|string   $disk
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertStored($filePath, $disk = null, $callback = null) {
        if ($disk instanceof Closure) {
            $callback = $disk;
            $disk = null;
        }
        $disk = $disk ?: 'default';
        $path = $this->resolve(carr::get($this->stored, $disk, []), $filePath);

        PHPUnit::assertNotNull($path, sprintf('%s is not stored on disk %s', $filePath, $disk));

        $this->assertCallback($callback, $this->stored[$disk][$path], "The file [{$filePath}] was not stored with the expected data.");
    }

    /**
     * @param string      $filePath
     * @param null|string $disk
     *
     * @return void
     */
    public function assertNotStored($filePath, $disk = null) {
        $disk = $disk ?: 'default';

        PHPUnit::assertNull($this->resolve(carr::get($this->stored, $disk, []), $filePath), sprintf('%s was stored on disk %s unexpectedly', $filePath, $disk));
    }

    /**
     * @param string        $filePath
     * @param null|string   $disk
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertQueued($filePath, $disk = null, $callback = null) {
        if ($disk instanceof Closure) {
            $callback = $disk;
            $disk = null;
        }
        $disk = $disk ?: 'default';
        $path = $this->resolve(carr::get($this->queued, $disk, []), $filePath);

        PHPUnit::assertNotNull($path, sprintf('%s is not queued for export on disk %s', $filePath, $disk));

        $this->assertCallback($callback, $this->queued[$disk][$path], "The file [{$filePath}] was not queued with the expected data.");
    }

    /**
     * @param array         $chain    kelas job yang diharapkan berurutan
     * @param string        $filePath
     * @param null|string   $disk
     *
     * @return void
     */
    public function assertQueuedWithChain(array $chain, $filePath = null, $disk = null) {
        $disk = $disk ?: 'default';
        $dispatches = carr::get($this->queuedDispatches, $disk, []);
        if ($filePath !== null) {
            $path = $this->resolve($dispatches, $filePath);
            PHPUnit::assertNotNull($path, sprintf('%s is not queued for export on disk %s', $filePath, $disk));
            $dispatches = [$dispatches[$path]];
        }

        $expected = array_map(function ($job) {
            return is_object($job) ? get_class($job) : (string) $job;
        }, $chain);
        $matched = false;
        foreach ($dispatches as $dispatch) {
            $actual = array_map(function ($job) {
                return is_object($job) ? get_class($job) : (string) $job;
            }, $dispatch->getChain());
            if ($actual === $expected) {
                $matched = true;
            }
        }

        PHPUnit::assertTrue($matched, 'The expected chain was not queued: [' . implode(', ', $expected) . '].');
    }

    /**
     * @param string        $exportClass
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertExportedInRaw($exportClass, $callback = null) {
        PHPUnit::assertArrayHasKey($exportClass, $this->raws, sprintf('%s is not exported in raw', $exportClass));

        $this->assertCallback($callback, $this->raws[$exportClass], "The export [{$exportClass}] was not exported in raw with the expected data.");
    }

    /**
     * @param string        $filePath
     * @param null|string   $disk
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertImported($filePath, $disk = null, $callback = null) {
        if ($disk instanceof Closure) {
            $callback = $disk;
            $disk = null;
        }
        $disk = $disk ?: 'default';
        $path = $this->resolve(carr::get($this->imported, $disk, []), $filePath);

        PHPUnit::assertNotNull($path, sprintf('%s is not imported on disk %s', $filePath, $disk));

        $this->assertCallback($callback, $this->imported[$disk][$path], "The file [{$filePath}] was not imported with the expected data.");
    }

    /**
     * @param array  $recorded kunci = nama berkas/path tercatat
     * @param string $expected
     *
     * @return null|string kunci tercatat yang cocok
     */
    protected function resolve(array $recorded, $expected) {
        if (!$this->matchByRegex) {
            return array_key_exists($expected, $recorded) ? $expected : null;
        }

        foreach (array_keys($recorded) as $name) {
            if (preg_match($expected, (string) $name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param null|callable $callback
     * @param mixed         $subject
     * @param string        $message
     *
     * @return void
     */
    protected function assertCallback($callback, $subject, $message) {
        $callback = $callback ?: function () {
            return true;
        };

        PHPUnit::assertTrue((bool) $callback($subject), $message);
    }
}
