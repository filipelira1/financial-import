<?php

namespace App\Application\Import;

use App\Application\Import\Exceptions\ImportCannotBeRetried;
use App\Domain\Import\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;

/**
 * Run a failed import again. Rows already stored are skipped by the idempotency key.
 */
final class RetryImportAction
{
    public function execute(Import $import): Import
    {
        // Conditional update: of two simultaneous retries, only one wins.
        $claimed = Import::query()
            ->whereKey($import->id)
            ->where('status', ImportStatus::Failed)
            ->update([
                'status' => ImportStatus::Pending,
                'failure_reason' => null,
                'finished_at' => null,
                'batch_id' => null,
            ]);

        if ($claimed === 0) {
            throw new ImportCannotBeRetried('Only a failed import can be retried.');
        }

        ProcessImportJob::dispatch($import->id);

        return $import->refresh();
    }
}
