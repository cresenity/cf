<?php

use Maatwebsite\Excel\Jobs\QueueImport;

class CExporter_ChunkReader {
    /**
     * @param CExporter_Concern_WithChunkReading $import
     * @param CExporter_Reader                   $reader
     * @param CExporter_File_TemporaryFile       $temporaryFile
     *
     * @return null|\CQueue_PendingDispatch
     */
    public function read(CExporter_Concern_WithChunkReading $import, CExporter_Reader $reader, CExporter_File_TemporaryFile $temporaryFile) {
        if ($import instanceof CExporter_Concern_WithEvents && isset($import->registerEvents()[CExporter_Event_BeforeImport::class])) {
            $reader->beforeImport($import);
        }

        $chunkSize = $import->chunkSize();
        $totalRows = $reader->getTotalRows();
        $worksheets = $reader->getWorksheets($import);

        if ($import instanceof CExporter_Concern_WithProgressBar) {
            $import->getConsoleOutput()->progressStart(array_sum($totalRows));
        }

        $jobs = new CCollection();
        foreach ($worksheets as $name => $sheetImport) {
            $startRow = CExporter_Import_HeadingRowExtractor::determineStartRow($sheetImport);
            $totalRows[$name] = $sheetImport instanceof CExporter_Concern_WithLimit ? $sheetImport->limit() : $totalRows[$name];

            for ($currentRow = $startRow; $currentRow <= $totalRows[$name]; $currentRow += $chunkSize) {
                $jobs->push(new CExporter_TaskQueue_ReadChunk(
                    $import,
                    $reader->getPhpSpreadsheetReader(),
                    $temporaryFile,
                    $name,
                    $sheetImport,
                    $currentRow,
                    $chunkSize
                ));
            }
        }

        $jobs->push(new CExporter_TaskQueue_AfterImportJob($import, $reader));

        if ($import instanceof CQueue_ShouldQueueInterface) {
            return CExporter_TaskQueue_QueueImport::withChain($jobs->toArray())->dispatch($import);
        }

        $jobs->each(function ($job) {
            try {
                CQueue::dispatcher()->dispatchNow($job);
            } catch (Throwable $e) {
                if (method_exists($job, 'failed')) {
                    $job->failed($e);
                }

                throw $e;
            } catch (Exception $e) {
                if (method_exists($job, 'failed')) {
                    $job->failed($e);
                }

                throw $e;
            }
        });

        if ($import instanceof CExporter_Concern_WithProgressBar) {
            $import->getConsoleOutput()->progressFinish();
        }

        unset($jobs);

        return null;
    }
}
