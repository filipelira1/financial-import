<?php

namespace App\Jobs;

use App\Application\Import\FinalizeImportAction;
use App\Application\Import\PlanImportAction;
use App\Domain\Import\Events\ImportFinished;
use App\Domain\Import\ImportStatus;
use App\Infrastructure\Csv\CsvChunk;
use App\Infrastructure\Csv\InvalidCsvFile;
use App\Models\Import;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Reads the file once to plan the chunks, then fans them out as a job batch.
 */
class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(public readonly int $importId)
    {
        $this->onQueue(config('imports.queue'));
    }

    public function handle(PlanImportAction $plan, FinalizeImportAction $finalize): void
    {
        $import = Import::query()->find($this->importId);

        // A retry of this job after its batch was dispatched must not open a second batch
        // (two finalizations). The retry endpoint clears batch_id before queueing again.
        if ($import === null || $import->status->isFinished() || $import->batch_id !== null) {
            return;
        }

        try {
            $chunks = $plan->execute($import);
        } catch (InvalidCsvFile $e) {
            // A broken file will not fix itself: fail now instead of retrying.
            $this->fail($e);

            return;
        }

        if ($chunks === []) {
            $finalize->execute($import->id);

            return;
        }

        $importId = $import->id;

        $batch = Bus::batch(array_map(fn (CsvChunk $chunk) => new ImportChunkJob($importId, $chunk), $chunks))
            ->name("import-{$importId}")
            ->onQueue(config('imports.queue'))
            ->allowFailures()
            ->finally(function (Batch $batch) use ($importId): void {
                app(FinalizeImportAction::class)->execute($importId, $batch->failedJobs);
            })
            ->dispatch();

        Import::query()->whereKey($importId)->update(['batch_id' => $batch->id]);
    }

    public function failed(?Throwable $exception): void
    {
        $import = Import::query()->find($this->importId);

        if ($import === null) {
            return;
        }

        $import->update([
            'status' => ImportStatus::Failed,
            'failure_reason' => $exception?->getMessage() ?? 'The import could not be processed.',
            'finished_at' => now(),
        ]);

        event(new ImportFinished($import->user_id, $import->id, ImportStatus::Failed));
    }
}
