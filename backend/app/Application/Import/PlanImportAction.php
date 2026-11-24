<?php

namespace App\Application\Import;

use App\Domain\Import\ImportStatus;
use App\Domain\Import\TransactionRowParser;
use App\Infrastructure\Csv\CsvChunk;
use App\Infrastructure\Csv\CsvReader;
use App\Infrastructure\Csv\InvalidCsvFile;
use App\Models\Import;
use Illuminate\Support\Facades\Storage;

/**
 * Mark the import as processing and work out the chunks to queue.
 */
final class PlanImportAction
{
    /**
     * @return list<CsvChunk>
     *
     * @throws InvalidCsvFile
     */
    public function execute(Import $import): array
    {
        $import->update([
            'status' => ImportStatus::Processing,
            'started_at' => $import->started_at ?? now(),
            'failure_reason' => null,
        ]);

        $plan = CsvReader::open(Storage::disk(config('imports.disk'))->path($import->stored_path))
            ->requireColumns(TransactionRowParser::COLUMNS)
            ->plan(max(1, (int) config('imports.chunk_size')));

        $import->update(['total_rows' => $plan['total']]);

        return $plan['chunks'];
    }
}
