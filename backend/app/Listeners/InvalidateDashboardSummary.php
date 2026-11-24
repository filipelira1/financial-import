<?php

namespace App\Listeners;

use App\Domain\Finance\Events\TransactionsImported;
use App\Domain\Import\Events\ImportFinished;
use App\Infrastructure\Cache\DashboardSummaryCache;

/**
 * Bulk inserts bypass Eloquent, so model observers never see imported rows:
 * the import announces them with domain events instead.
 */
final readonly class InvalidateDashboardSummary
{
    public function __construct(private DashboardSummaryCache $cache) {}

    /**
     * Every chunk that stored rows. When an import finishes, invalidate once more: if a
     * worker died between a chunk's commit and its event, the retried chunk inserts
     * nothing and announces nothing, and only this second signal refreshes the totals.
     */
    public function handle(TransactionsImported|ImportFinished $event): void
    {
        $this->cache->invalidate($event->userId);
    }
}
