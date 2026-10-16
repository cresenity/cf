<?php

class CLogger_Reader_LogReader {
    /**
     * Cached LogReader instances.
     */
    public static array $instances = [];

    protected CLogger_Reader_LogFile $file;

    /**
     * Contains an index of file positions where each log entry is located in.
     */
    protected CLogger_Reader_LogIndex $logIndex;

    protected ?int $limit = null;

    protected ?string $query = null;

    protected ?int $onlyShowIndex = null;

    protected bool $lazyScanning = false;

    /**
     * @var null|resource
     */
    protected $fileHandle = null;

    protected int $mtimeBeforeScan;

    protected string $direction = CLogger_Reader_Direction::FORWARD;

    public function __construct(CLogger_Reader_LogFile $file) {
        $this->file = $file;
    }

    public static function instance(CLogger_Reader_LogFile $file): self {
        if (!isset(self::$instances[$file->path])) {
            self::$instances[$file->path] = new self($file);
        }

        return self::$instances[$file->path];
    }

    public static function clearInstance(CLogger_Reader_LogFile $file): void {
        if (isset(self::$instances[$file->path])) {
            unset(self::$instances[$file->path]);
        }
    }

    public function index(): CLogger_Reader_LogIndex {
        return $this->file->index($this->query);
    }

    /**
     * Load only the provided log levels.
     *
     * @alias setLevels
     *
     * @param null|string|array $levels
     */
    public function only($levels = null): self {
        return $this->setLevels($levels);
    }

    /**
     * Load only the provided log levels.
     *
     * @param null|string|array $levels
     */
    public function setLevels($levels = null): self {
        $this->index()->forLevels($levels);

        return $this;
    }

    public function allLevels(): self {
        $this->index()->forLevels(null);

        return $this;
    }

    /**
     * Load all log levels except the provided ones.
     *
     * @alias exceptLevels
     *
     * @param null|string|array $levels
     *
     * @return $this
     */
    public function except($levels = null): self {
        return $this->exceptLevels($levels);
    }

    /**
     * Load all log levels except the provided ones.
     *
     * @param null|string|array $levels
     *
     * @return $this
     */
    public function exceptLevels($levels = null): self {
        $levels = null;

        if (is_array($levels)) {
            $levels = array_map('strtolower', array_filter($levels));
            $levels = array_diff(self::getDefaultLevels(), $levels);
        } elseif (is_string($levels)) {
            $level = strtolower($levels);
            $levels = array_diff(self::getDefaultLevels(), [$level]);
        }

        $this->index()->forLevels($levels);

        return $this;
    }

    public static function getDefaultLevels(): array {
        return CLogger_Level::caseValues();
    }

    public function isOpen(): bool {
        return is_resource($this->fileHandle);
    }

    public function isClosed(): bool {
        return !$this->isOpen();
    }

    /**
     * Open the log file for reading. Most other methods will open the file automatically if needed.
     *
     * @throws \Exception
     */
    public function open(): self {
        if ($this->isOpen()) {
            return $this;
        }

        $this->fileHandle = fopen($this->file->path, 'r');

        if ($this->fileHandle === false) {
            throw new \Exception('Could not open "' . $this->file->path . '" for reading.');
        }

        if ($this->requiresScan() && !$this->lazyScanning) {
            $this->scan();
        } else {
            $this->reset();
        }

        return $this;
    }

    /**
     * Close the file handle.
     *
     * @throws \Exception
     */
    public function close(): self {
        if ($this->isClosed()) {
            return $this;
        }

        if (fclose($this->fileHandle)) {
            $this->fileHandle = null;
        } else {
            throw new \Exception('Could not close the file "' . $this->file->path . '".');
        }

        return $this;
    }

    public function reverse(): self {
        $this->direction = CLogger_Reader_Direction::BACKWARD;
        $this->index()->reverse();

        return $this->reset();
    }

    public function setDirection(?string $direction = null): self {
        $this->direction = $direction === CLogger_Reader_Direction::BACKWARD
            ? CLogger_Reader_Direction::BACKWARD
            : CLogger_Reader_Direction::FORWARD;
        $this->index()->setDirection($this->direction);

        return $this;
    }

    public function skip(int $number): self {
        $this->index()->skip($number);

        return $this;
    }

    public function onlyShow(int $targetIndex = 0): self {
        $this->onlyShowIndex = $targetIndex;

        return $this;
    }

    public function limit(int $number): self {
        $this->index()->limit($number);

        return $this;
    }

    public function setQuery(?string $query = null): self {
        $this->close();

        if (!empty($query) && cstr::startsWith($query, 'log-index:')) {
            $this->query = null;
            $this->only(null);
            $this->onlyShow(intval(explode(':', $query)[1]));
        } elseif (!empty($query)) {
            $query = '/' . $query . '/i';

            CLogger_Reader_Util_Utils::validateRegex($query);

            $this->query = $query;
        } else {
            $this->query = null;
        }

        $this->index()->setQuery($this->query);

        return $this;
    }

    public function search(?string $query = null): self {
        return $this->setQuery($query);
    }

    public function lazyScanning($lazy = true): self {
        $this->lazyScanning = $lazy;

        return $this;
    }

    /**
     * This method scans the whole file quickly to index the logs in order to speed up
     * the retrieval of individual logs.
     *
     * @throws \Exception
     */
    public function scan(?int $maxBytesToScan = null, bool $force = false): self {
        if ($this->isClosed()) {
            $this->open();
        }

        if (!$this->requiresScan() && !$force) {
            return $this;
        }

        if ($this->numberOfNewBytes() < 0) {
            // the file reduced in size... something must've gone wrong, so let's
            // force a full re-index.
            $force = true;
        }

        if ($force) {
            // when forcing, make sure we start from scratch and reset everything.
            $this->index()->clearCache();
        }

        $this->mtimeBeforeScan = $this->file->mtime();

        // we don't care about the selected levels here, we should scan everything
        $logIndex = $this->index();
        $levels = self::getDefaultLevels();
        $logMatchPattern = CLogger_Reader::logMatchPattern();
        $earliest_timestamp = $this->file->getMetadata('earliest_timestamp');
        $latest_timestamp = $this->file->getMetadata('latest_timestamp');
        $currentLog = '';
        $currentLogLevel = '';
        $currentTimestamp = null;
        $currentIndex = $this->index()->getLastScannedIndex();
        fseek($this->fileHandle, $this->index()->getLastScannedFilePosition());
        $currentLogPosition = ftell($this->fileHandle);
        $lastPositionToScan = isset($maxBytesToScan) ? ($currentLogPosition + $maxBytesToScan) : null;

        while ((!isset($lastPositionToScan) || $currentLogPosition < $lastPositionToScan)
            && ($line = fgets($this->fileHandle, 1024)) !== false
        ) {
            /**
             * $matches[0] - the full line being checked
             * $matches[1] - the full timestamp in-between the square brackets, including the optional microseconds
             *               and the optional timezone offset
             * $matches[2] - the optional microseconds
             * $matches[3] - the optional timezone offset, like `+02:00` or `-05:30`.
             */
            $matches = [];
            if (preg_match($logMatchPattern, $line, $matches) === 1) {
                if ($currentLog !== '') {
                    if (is_null($this->query) || preg_match($this->query, $currentLog)) {
                        $logIndex->addToIndex($currentLogPosition, $currentTimestamp, $currentLogLevel, $currentIndex);
                    }

                    $currentLog = '';
                    $currentIndex++;
                }

                $currentTimestamp = strtotime($matches[1] ?? '');
                $earliest_timestamp = min($earliest_timestamp ?? $currentTimestamp, $currentTimestamp);
                $latest_timestamp = max($latest_timestamp ?? $currentTimestamp, $currentTimestamp);
                $currentLogPosition = ftell($this->fileHandle) - strlen($line);
                $lowercaseLine = strtolower($line);

                foreach ($levels as $level) {
                    if (strpos($lowercaseLine, '.' . $level) || strpos($lowercaseLine, $level . ':')) {
                        $currentLogLevel = $level;

                        break;
                    }
                }

                // Because we matched this line as the beginning of a new log,
                // and we have already processed the previously set $currentLog variable,
                // we can safely set this to the current line we scanned.
                $currentLog = $line;
            } elseif ($currentLog !== '') {
                // This check makes sure we don't keep adding rubbish content to the log
                // if we haven't found a proper matching beginning of a log entry yet.
                // So any content (empty lines, unrelated text) at the beginning of the log file
                // will be ignored until the first matching log entry comes up.
                $currentLog .= $line;
            }
        }

        if ($currentLog !== '' && preg_match($logMatchPattern, $currentLog) === 1) {
            if ((is_null($this->query) || preg_match($this->query, $currentLog))) {
                $logIndex->addToIndex($currentLogPosition, $currentTimestamp, $currentLogLevel, $currentIndex);
                $currentIndex++;
            }
        }

        $logIndex->setLastScannedIndex($currentIndex);
        $logIndex->setLastScannedFilePosition(ftell($this->fileHandle));
        $logIndex->save();

        $this->file->setMetadata('name', $this->file->name);
        $this->file->setMetadata('path', $this->file->path);
        $this->file->setMetadata('size', $this->file->size());
        $this->file->setMetadata('earliest_timestamp', $this->index()->getEarliestTimestamp());
        $this->file->setMetadata('latest_timestamp', $this->index()->getLatestTimestamp());
        $this->file->setMetadata('last_scanned_file_position', ftell($this->fileHandle));
        $this->file->addRelatedIndex($logIndex);

        $this->file->saveMetadata();

        // Let's reset the position in preparation for real log reads.
        rewind($this->fileHandle);

        return $this->reset();
    }

    public function reset(): self {
        $this->index()->reset();

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return array|LevelCount[]
     */
    public function getLevelCounts(): array {
        if ($this->isClosed()) {
            $this->open();
        }

        $selectedLevels = $this->index()->getSelectedLevels();

        return $this->index()->getLevelCounts()->map(function (int $count, string $level) use ($selectedLevels) {
            return new CLogger_Reader_LevelCount(
                CLogger_Level::from($level),
                $count,
                in_array($level, $selectedLevels)
            );
        })->toArray();
    }

    /**
     * @return array|CLogger_Reader_Log[]
     */
    public function all() {
        $this->reset();

        return $this->get();
    }

    /**
     * @return array|CLogger_Reader_Log[]
     */
    public function get(?int $limit = null) {
        if (!is_null($limit)) {
            $this->limit($limit);
        }

        $logs = [];

        while ($log = $this->next()) {
            $logs[] = $log;
        }

        return $logs;
    }

    /**
     * @param int $index
     *
     * @return null|CLogger_Reader_Log
     */
    public function getLogAtIndex($index) {
        $position = $this->index()->getPositionForIndex($index);
        if (is_null($position)) {
            return null;
        }

        list($level, $text, $position) = $this->getLogText($index, $position);

        // If we did not find any logs, this means either the file is empty, or
        // we have already reached the end of file. So we return early.
        if ($text === '') {
            return null;
        }

        return $this->makeLog($text, $position, $index);
    }

    /**
     * @return null|CLogger_Reader_Log
     */
    #[\ReturnTypeWillChange]
    public function next() {
        // We open it here to make we also check for possible need of index re-building.
        if ($this->isClosed()) {
            $this->open();
        }

        list($index, $position) = $this->index()->next();

        if (is_null($index)) {
            return null;
        }

        list($level, $text, $position) = $this->getLogText($index, $position);

        if (empty($text)) {
            return null;
        }

        return $this->makeLog($text, $position, $index);
    }

    /**
     * @return int
     */
    public function total() {
        return $this->index()->count();
    }

    /**
     * Alias for total().
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count() {
        return $this->total();
    }

    /**
     * @param null|Closure $itemCallback
     *
     * @return CPagination_LengthAwarePaginator
     */
    public function paginate(int $perPage = 25, ?int $page = null, $itemCallback = null) {
        $page = $page ?: CPagination_Paginator::resolveCurrentPage('page');

        if (!is_null($this->onlyShowIndex)) {
            $log = $this->reset()->getLogAtIndex($this->onlyShowIndex);

            return new CPagination_LengthAwarePaginator(
                $log ? [$log] : [],
                $log ? 1 : 0,
                $perPage,
                $page
            );
        }

        $this->reset()->skip(max(0, $page - 1) * $perPage);
        $items = $this->get($perPage);
        if ($itemCallback != null) {
            $items = c::collect($items)->map($itemCallback)->toArray();
        }

        return new CPagination_LengthAwarePaginator(
            $items,
            $this->total(),
            $perPage,
            $page
        );
    }

    protected function makeLog(string $text, int $filePosition, int $index) {
        return new CLogger_Reader_Log($index, $text, $this->file->identifier, $filePosition);
    }

    /**
     * @throws \Exception
     *
     * @return null|array Returns an array, [$level, $text, $position]
     */
    protected function getLogText(int $index, int $position, bool $fullText = false): ?array {
        if ($this->isClosed()) {
            $this->open();
        }

        fseek($this->fileHandle, $position, SEEK_SET);

        $currentLog = '';
        $currentLogLevel = '';

        while (($line = fgets($this->fileHandle)) !== false) {
            if (preg_match(CLogger_Reader::logMatchPattern(), $line) === 1) {
                if ($currentLog !== '') {
                    // found the next log, so let's stop the loop and return the log we found
                    break;
                }

                $lowercaseLine = strtolower($line);
                foreach (self::getDefaultLevels() as $level) {
                    if (strpos($lowercaseLine, '.' . $level) || strpos($lowercaseLine, $level . ':')) {
                        $currentLogLevel = $level;

                        break;
                    }
                }
            }

            $currentLog .= $line;
        }

        return [$currentLogLevel, $currentLog, $position];
    }

    /**
     * @return int
     */
    public function numberOfNewBytes() {
        $lastScannedFilePosition = $this->file->getLastScannedFilePositionForQuery($this->query);

        if (is_null($lastScannedFilePosition)) {
            $lastScannedFilePosition = $this->index()->getLastScannedFilePosition();
        }

        return $this->file->size() - $lastScannedFilePosition;
    }

    public function requiresScan(): bool {
        if (isset($this->mtimeBeforeScan) && ($this->file->mtime() > $this->mtimeBeforeScan || $this->file->mtime() === time())) {
            // The file has been modified since the last scan in this request.
            // Let's only request another scan if it's not the last chunk (smaller than lazyScanChunkSize).
            // The last chunk will be scanned until the end before hitting this logic again,
            // and by then the only appended bytes will be from the current request and thus return false.
            return $this->numberOfNewBytes() >= CLogger_Reader::lazyScanChunkSize();
        }

        return $this->numberOfNewBytes() !== 0;
    }

    public function percentScanned(): int {
        if ($this->file->size() <= 0) {
            // empty file, so assume it has been fully scanned.
            return 100;
        }

        return 100 - intval(($this->numberOfNewBytes() / $this->file->size() * 100));
    }

    public function __destruct() {
        $this->close();
    }
}
