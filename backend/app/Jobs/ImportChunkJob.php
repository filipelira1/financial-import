<?php

namespace App\Jobs;

use App\Application\Import\ImportChunkAction;
use App\Infrastructure\Csv\CsvChunk;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One chunk of rows. The payload is an offset in the file, not the rows themselves.
 */
class ImportChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly int $importId,
        public readonly CsvChunk $chunk,
    ) {}

    public function handle(ImportChunkAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->importId, $this->chunk);
    }
}
