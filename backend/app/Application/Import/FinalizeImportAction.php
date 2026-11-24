<?php

namespace App\Application\Import;

use App\Domain\Import\Events\ImportFinished;
use App\Domain\Import\ImportStatus;
use App\Models\Import;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Close an import once every chunk has run, checking that no row went missing.
 */
final readonly class FinalizeImportAction
{
    public function __construct(private Dispatcher $events) {}

    public function execute(int $importId, int $failedChunks = 0): Import
    {
        $import = Import::query()->findOrFail($importId);

        // The tables are the source of truth; the live counters only feed the progress bar.
        $imported = $import->transactions()->count();
        $failed = $import->failures()->count();
        $total = (int) $import->total_rows;

        [$status, $reason] = match (true) {
            $failedChunks > 0 => [
                ImportStatus::Failed,
                "{$failedChunks} chunk(s) could not be processed. Retry the import to finish it; stored rows are not duplicated.",
            ],
            $imported + $failed !== $total => [
                ImportStatus::Failed,
                "Row count mismatch: {$imported} imported + {$failed} rejected, but the file has {$total} rows.",
            ],
            $failed > 0 => [ImportStatus::CompletedWithErrors, null],
            default => [ImportStatus::Completed, null],
        };

        $import->update([
            'imported_rows' => $imported,
            'failed_rows' => $failed,
            'status' => $status,
            'failure_reason' => $reason,
            'finished_at' => now(),
        ]);

        // The data now lives in the tables. A failed import keeps its file so it can be retried.
        if ($status !== ImportStatus::Failed) {
            Storage::disk(config('imports.disk'))->delete($import->stored_path);
        }

        DB::afterCommit(fn () => $this->events->dispatch(new ImportFinished($import->user_id, $import->id, $status)));

        return $import;
    }
}
