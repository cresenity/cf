<?php

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CLogger_Reader_LogFile {
    use CLogger_Reader_Concern_LogFile_HasMetaData;
    use CLogger_Reader_Concern_LogFile_CanCacheData;

    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $identifier;

    /**
     * @var string
     */
    public $subFolder = '';

    /**
     * @var array
     */
    private $logIndexCache;

    public function __construct(string $path) {
        $this->path = $path;
        $this->name = basename($path);
        $this->identifier = CLogger_Reader_Util_Utils::shortMd5($path) . '-' . $this->name;

        // Let's remove the file name because we already know it.
        $this->subFolder = dirname($path) === '.' ? '' : rtrim(dirname($path), DIRECTORY_SEPARATOR);

        $this->loadMetadata();
    }

    /**
     * @param null|string $query
     *
     * @return CLogger_Reader_LogIndex
     */
    public function index($query = null) {
        if (!isset($this->logIndexCache[$query])) {
            $this->logIndexCache[$query] = new CLogger_Reader_LogIndex($this, $query);
        }

        return $this->logIndexCache[$query];
    }

    /**
     * @return CLogger_Reader_LogReader
     */
    public function logs() {
        return CLogger_Reader_LogReader::instance($this);
    }

    public function size(): int {
        clearstatcache();

        return filesize($this->path);
    }

    public function sizeInMB(): float {
        return $this->size() / 1024 / 1024;
    }

    public function sizeFormatted(): string {
        return CLogger_Reader_Util_Utils::bytesForHumans($this->size());
    }

    public function subFolderIdentifier(): string {
        return CLogger_Reader_Util_Utils::shortMd5($this->subFolder);
    }

    /**
     * @return BinaryFileResponse
     */
    public function download() {
        return c::response()->download($this->path);
    }

    public function addRelatedIndex(CLogger_Reader_LogIndex $logIndex): void {
        $relatedIndices = c::collect($this->getMetadata('related_indices', []));
        $relatedIndices[$logIndex->identifier] = carr::only(
            $logIndex->getMetadata(),
            ['query', 'last_scanned_file_position']
        );

        $this->setMetadata('related_indices', $relatedIndices->toArray());
    }

    public function getLastScannedFilePositionForQuery(?string $query = ''): ?int {
        foreach ($this->getMetadata('related_indices', []) as $indexIdentifier => $indexMetadata) {
            if ($query === $indexMetadata['query']) {
                return $indexMetadata['last_scanned_file_position'] ?? 0;
            }
        }

        return null;
    }

    public function mtime(): int {
        return is_file($this->path) ? filemtime($this->path) : 0;
    }

    public function earliestTimestamp(): int {
        return $this->getMetadata('earliest_timestamp') ?? $this->mtime();
    }

    public function latestTimestamp(): int {
        return $this->getMetadata('latest_timestamp') ?? $this->mtime();
    }

    public function scan(?int $maxBytesToScan = null, bool $force = false): void {
        $this->logs()->scan($maxBytesToScan, $force);
    }

    public function requiresScan(): bool {
        return $this->logs()->requiresScan();
    }

    /**
     * @param null|string $query regex, atau teks literal bila bukan regex valid
     *
     * @return CLogger_Reader_LogReader
     */
    public function search($query = null) {
        return $this->logs()->search($query);
    }

    public function delete(): void {
        $this->clearCache();
        unlink($this->path);
        CLogger_Reader_Event_LogFileDeleted::dispatch($this);
    }
}
