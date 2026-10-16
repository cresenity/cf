<?php

use Carbon\CarbonInterface;

class CLogger_Reader_LogIndex {
    use CLogger_Reader_Concern_LogIndex_HasMetadata;
    use CLogger_Reader_Concern_LogIndex_CanCacheIndex;
    use CLogger_Reader_Concern_LogIndex_CanFilterIndex;
    use CLogger_Reader_Concern_LogIndex_CanIterateIndex;
    use CLogger_Reader_Concern_LogIndex_CanSplitIndexIntoChunks;
    use CLogger_Reader_Concern_LogIndex_PreservesIndexingProgress;

    const DEFAULT_CHUNK_SIZE = 20_000;

    /**
     * @var string
     */
    public $identifier;

    /**
     * @var CLogger_Reader_LogFile
     */
    public $file;

    /**
     * @var int
     */
    protected $nextLogIndexToCreate;

    /**
     * @var int
     */
    protected $lastScannedFilePosition;

    /**
     * @var int
     */
    protected $lastScannedIndex;

    /**
     * @var string
     */
    protected $query;

    /**
     * @param null|string $query
     */
    public function __construct(CLogger_Reader_LogFile $file, $query = null) {
        $this->file = $file;
        $this->query = $query;
        $this->identifier = CLogger_Reader_Util_Utils::shortMd5($this->query ?? '');
        $this->loadMetadata();
    }

    /**
     * @param int                 $filePosition
     * @param int|CarbonInterface $timestamp
     * @param string              $severity
     * @param null|int            $index
     *
     * @return int
     */
    public function addToIndex($filePosition, $timestamp, $severity, $index = null) {
        $logIndex = $index ?? $this->nextLogIndexToCreate ?? 0;

        if ($timestamp instanceof CarbonInterface) {
            $timestamp = $timestamp->timestamp;
        }

        $this->getCurrentChunk()->addToIndex($logIndex, $filePosition, $timestamp, $severity);

        $this->nextLogIndexToCreate = $logIndex + 1;

        if ($this->getCurrentChunk()->size >= $this->getMaxChunkSize()) {
            $this->rotateCurrentChunk();
        }

        return $logIndex;
    }

    public function getPositionForIndex(int $indexToFind): ?int {
        $itemsSkipped = 0;

        foreach ($this->getChunkDefinitions() as $chunkDefinition) {
            if (($itemsSkipped + $chunkDefinition['size']) <= $indexToFind) {
                // not in this index, let's move on
                $itemsSkipped += $chunkDefinition['size'];

                continue;
            }

            foreach ($this->getChunkData($chunkDefinition['index']) as $timestamp => $tsIndex) {
                foreach ($tsIndex as $level => $levelIndex) {
                    foreach ($levelIndex as $index => $position) {
                        if ($index === $indexToFind) {
                            return $position;
                        }
                    }
                }
            }
        }

        return null;
    }

    public function get(?int $limit = null): array {
        $results = [];
        $itemsAdded = 0;
        $limit = $limit ?? $this->limit;
        $skip = $this->skip;
        $chunkDefinitions = $this->getChunkDefinitions();

        $this->sortKeys($chunkDefinitions);

        foreach ($chunkDefinitions as $chunkDefinition) {
            if ($this->chunkIsOutsideDateFilter($chunkDefinition)) {
                continue;
            }
            if (isset($skip) && $this->chunkIsWithinDateFilter($chunkDefinition)) {
                $relevantItemsInChunk = $this->getRelevantItemsInChunk($chunkDefinition);

                if ($relevantItemsInChunk <= $skip) {
                    $skip -= $relevantItemsInChunk;

                    continue;
                }
            }

            $chunk = $this->getChunkData($chunkDefinition['index']);

            if (empty($chunk)) {
                continue;
            }

            $this->sortKeys($chunk);

            foreach ($chunk as $timestamp => $tsIndex) {
                if (isset($this->filterFrom) && $timestamp < $this->filterFrom) {
                    continue;
                }
                if (isset($this->filterTo) && $timestamp > $this->filterTo) {
                    continue;
                }

                // Timestamp is valid, let's start adding
                if (!isset($results[$timestamp])) {
                    $results[$timestamp] = [];
                }

                foreach ($tsIndex as $level => $levelIndex) {
                    if (isset($this->filterLevels) && !in_array($level, $this->filterLevels)) {
                        continue;
                    }

                    // severity is valid, let's start adding
                    if (!isset($results[$timestamp][$level])) {
                        $results[$timestamp][$level] = [];
                    }

                    $this->sortKeys($levelIndex);

                    foreach ($levelIndex as $idx => $position) {
                        if ($skip > 0) {
                            $skip--;

                            continue;
                        }

                        $results[$timestamp][$level][$idx] = $position;

                        if (isset($limit) && ++$itemsAdded >= $limit) {
                            break 4;
                        }
                    }

                    if (empty($results[$timestamp][$level])) {
                        unset($results[$timestamp][$level]);
                    }
                }

                if (empty($results[$timestamp])) {
                    unset($results[$timestamp]);
                }
            }
        }

        return $results;
    }

    public function getFlatIndex(?int $limit = null): array {
        $results = [];
        $itemsAdded = 0;
        $limit = $limit ?? $this->limit;
        $skip = $this->skip;
        $chunkDefinitions = $this->getChunkDefinitions();

        $this->sortKeys($chunkDefinitions);

        foreach ($chunkDefinitions as $chunkDefinition) {
            if ($this->chunkIsOutsideDateFilter($chunkDefinition)) {
                continue;
            }
            if (isset($skip) && $this->chunkIsWithinDateFilter($chunkDefinition)) {
                $relevantItemsInChunk = $this->getRelevantItemsInChunk($chunkDefinition);

                if ($relevantItemsInChunk <= $skip) {
                    $skip -= $relevantItemsInChunk;

                    continue;
                }
            }

            $chunk = $this->getChunkData($chunkDefinition['index']);

            if (is_null($chunk)) {
                continue;
            }

            $this->sortKeys($chunk);

            foreach ($chunk as $timestamp => $tsIndex) {
                if (isset($this->filterFrom) && $timestamp < $this->filterFrom) {
                    continue;
                }
                if (isset($this->filterTo) && $timestamp > $this->filterTo) {
                    continue;
                }

                $itemsWithinThisTimestamp = [];

                foreach ($tsIndex as $level => $levelIndex) {
                    if (isset($this->filterLevels) && !in_array($level, $this->filterLevels)) {
                        continue;
                    }

                    foreach ($levelIndex as $idx => $filePosition) {
                        $itemsWithinThisTimestamp[$idx] = $filePosition;
                    }
                }

                $this->sortKeys($itemsWithinThisTimestamp);

                foreach ($itemsWithinThisTimestamp as $idx => $filePosition) {
                    if ($skip > 0) {
                        $skip--;

                        continue;
                    }

                    $results[$idx] = $filePosition;

                    if (isset($limit) && ++$itemsAdded >= $limit) {
                        break 3;
                    }
                }
            }
        }

        return $results;
    }

    public function save(): void {
        if (isset($this->currentChunk)) {
            $this->saveChunkToCache($this->currentChunk);
        }

        $this->saveMetadata();
    }

    public function getLevelCounts(): CCollection {
        $counts = c::collect(CLogger_Level::caseValues())->mapWithKeys(fn ($case) => [$case => 0]);

        if (!$this->hasDateFilters()) {
            // without date filters, we can use a faster approach
            foreach ($this->getChunkDefinitions() as $chunkDefinition) {
                foreach ($chunkDefinition['level_counts'] as $severity => $count) {
                    $counts[$severity] += $count;
                }
            }
        } else {
            foreach ($this->get() as $timestamp => $tsIndex) {
                foreach ($tsIndex as $severity => $logIndex) {
                    $counts[$severity] += count($logIndex);
                }
            }
        }

        return $counts;
    }

    /**
     * @deprecated Will be removed in v2.0. Please use LogIndex::count()
     */
    public function total(): int {
        return $this->count();
    }

    public function count(): int {
        return array_reduce($this->getChunkDefinitions(), function ($sum, $chunkDefinition) {
            if ($this->chunkIsOutsideDateFilter($chunkDefinition)) {
                return $sum;
            }
            if ($this->chunkIsWithinDateFilter($chunkDefinition)) {
                return $sum + $this->getRelevantItemsInChunk($chunkDefinition);
            }

            // chunk hanya sebagian masuk filter tanggal: hitung dari isinya
            foreach ($this->getChunkData($chunkDefinition['index']) ?? [] as $timestamp => $tsIndex) {
                if ((isset($this->filterFrom) && $timestamp < $this->filterFrom) || (isset($this->filterTo) && $timestamp > $this->filterTo)) {
                    continue;
                }
                foreach ($tsIndex as $level => $levelIndex) {
                    if (!isset($this->filterLevels) || in_array($level, $this->filterLevels)) {
                        $sum += count($levelIndex);
                    }
                }
            }

            return $sum;
        }, 0);
    }

    protected function sortKeys(array &$array): void {
        if ($this->isBackward()) {
            krsort($array);
        } else {
            ksort($array);
        }
    }
}
