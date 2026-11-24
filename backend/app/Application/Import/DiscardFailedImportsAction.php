<?php

namespace App\Application\Import;

use App\Domain\Import\ImportStatus;
use App\Infrastructure\Cache\DashboardSummaryCache;
use App\Models\Import;
use Illuminate\Support\Facades\Storage;

/**
 * A failed import keeps its file so it can be retried. If nobody retries it within the
 * grace period, discard it whole: the file, the import and the rows it stored part-way
 * (half an import in the totals is worse than none). The same file can then be uploaded again.
 */
final readonly class DiscardFailedImportsAction
{
    public function __construct(private DashboardSummaryCache $dashboard) {}

    /**
     * @return int how many imports were discarded
     */
    public function execute(int $olderThanDays): int
    {
        $discarded = 0;

        Import::query()
            ->where('status', ImportStatus::Failed)
            ->where('finished_at', '<', now()->subDays($olderThanDays))
            ->each(function (Import $import) use (&$discarded): void {
                Storage::disk(config('imports.disk'))->delete($import->stored_path);

                // Transactions and failures go with it (foreign keys cascade). Cascades bypass
                // Eloquent, so the dashboard is told explicitly.
                $import->delete();
                $this->dashboard->invalidate($import->user_id);
                $discarded++;
            });

        return $discarded;
    }
}
