<?php

trait CLogger_Reader_Concern_LogIndex_CanSplitIndexIntoChunks {
    protected int $maxChunkSize;

    /**
     * @var array
     */
    protected $currentChunkDefinition;

    /**
     * @var CLogger_Reader_LogIndexChunk
     */
    protected $currentChunk;

    /**
     * @var array
     */
    protected $chunkDefinitions = [];

    /**
     * @throws CLogger_Reader_Exception_InvalidChunkSizeException
     */
    public function setMaxChunkSize(int $size): void {
        if ($size < 1) {
            throw new CLogger_Reader_Exception_InvalidChunkSizeException($size . ' is not a valid chunk size. Must be higher than zero.');
        }

        $this->maxChunkSize = $size;
    }

    public function getMaxChunkSize(): int {
        return $this->maxChunkSize;
    }

    public function getCurrentChunk(): CLogger_Reader_LogIndexChunk {
        if (!isset($this->currentChunk)) {
            $this->currentChunk = CLogger_Reader_LogIndexChunk::fromDefinitionArray($this->currentChunkDefinition);

            if ($this->currentChunk->size > 0) {
                $this->currentChunk->data = $this->getChunkDataFromCache($this->currentChunk->index, []);
            }
        }

        return $this->currentChunk;
    }

    public function getChunkDefinitions(): array {
        return [
            ...$this->chunkDefinitions,
            $this->getCurrentChunk()->toArray(),
        ];
    }

    public function getChunkDefinition(int $index): ?array {
        return $this->getChunkDefinitions()[$index] ?? null;
    }

    public function getChunkCount(): int {
        return count($this->getChunkDefinitions());
    }

    public function getChunkData(int $index): ?array {
        $currentChunk = $this->getCurrentChunk();

        if ($index === c::optional($currentChunk)->index) {
            $chunkData = $currentChunk->data ?? [];
        } else {
            $chunkData = $this->getChunkDataFromCache($index);
        }

        return $chunkData;
    }

    protected function rotateCurrentChunk(): void {
        $this->saveChunkToCache($this->currentChunk);

        $this->chunkDefinitions[] = $this->currentChunk->toArray();

        $this->currentChunk = new CLogger_Reader_LogIndexChunk([], $this->currentChunk->index + 1, 0);

        $this->saveMetadata();
    }

    /**
     * Chunk seluruhnya di dalam filter tanggal (atau tidak ada filter tanggal), jadi level_counts-nya
     * bisa dipakai apa adanya tanpa membuka isi chunk.
     */
    protected function chunkIsWithinDateFilter(array $chunkDefinition): bool {
        if (!$this->hasDateFilters()) {
            return true;
        }
        $earliest = $chunkDefinition['earliest_timestamp'] ?? null;
        $latest = $chunkDefinition['latest_timestamp'] ?? null;
        if ($earliest === null || $latest === null) {
            return false;
        }

        return (!isset($this->filterFrom) || $earliest >= $this->filterFrom)
            && (!isset($this->filterTo) || $latest <= $this->filterTo);
    }

    /**
     * Chunk seluruhnya di luar filter tanggal - tidak ada log yang relevan di dalamnya.
     */
    protected function chunkIsOutsideDateFilter(array $chunkDefinition): bool {
        if (!$this->hasDateFilters()) {
            return false;
        }
        $earliest = $chunkDefinition['earliest_timestamp'] ?? null;
        $latest = $chunkDefinition['latest_timestamp'] ?? null;
        if ($earliest === null || $latest === null) {
            return false;
        }

        return (isset($this->filterTo) && $earliest > $this->filterTo)
            || (isset($this->filterFrom) && $latest < $this->filterFrom);
    }

    protected function getRelevantItemsInChunk(array $chunkDefinition): int {
        $relevantItemsInChunk = 0;

        foreach ($chunkDefinition['level_counts'] as $level => $count) {
            if (!isset($this->filterLevels) || in_array($level, $this->filterLevels)) {
                $relevantItemsInChunk += $count;
            }
        }

        return $relevantItemsInChunk;
    }
}
