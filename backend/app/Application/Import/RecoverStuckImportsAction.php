<?php

namespace App\Application\Import;

use App\Domain\Import\Events\ImportFinished;
use App\Domain\Import\ImportStatus;
use App\Models\Import;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * An import that stopped making progress (its batch's final callback never ran, its
 * worker was lost...) would show "processing" forever. Every chunk touches updated_at,
 * so an import untouched for a long time is stuck: fail it so the user can retry.
 */
final readonly class RecoverStuckImportsAction
{
    public function __construct(private Dispatcher $events) {}

    /**
     * @return int how many imports were recovered
     */
    public function execute(int $idleMinutes): int
    {
        $recovered = 0;

        Import::query()
            ->whereIn('status', [ImportStatus::Pending, ImportStatus::Processing])
            ->where('updated_at', '<', now()->subMinutes($idleMinutes))
            ->each(function (Import $import) use ($idleMinutes, &$recovered): void {
                // Conditional update: skip it if it moved on since it was read.
                $claimed = Import::query()
                    ->whereKey($import->id)
                    ->whereIn('status', [ImportStatus::Pending, ImportStatus::Processing])
                    ->update([
                        'status' => ImportStatus::Failed,
                        'failure_reason' => "No progress for {$idleMinutes} minutes. Retry the import to finish it; stored rows are not duplicated.",
                        'finished_at' => now(),
                    ]);

                if ($claimed === 1) {
                    $recovered++;
                    $this->events->dispatch(new ImportFinished($import->user_id, $import->id, ImportStatus::Failed));
                }
            });

        return $recovered;
    }
}
