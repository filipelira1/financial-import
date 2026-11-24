<?php

namespace App\Listeners;

use App\Domain\Finance\Events\TransactionsImported;
use App\Infrastructure\Cache\DashboardSummaryCache;

/**
 * Bulk inserts bypass Eloquent, so model observers never see imported rows:
 * the import announces them with a domain event instead.
 */
final readonly class InvalidateDashboardSummary
{
    public function __construct(private DashboardSummaryCache $cache) {}

    public function handle(TransactionsImported $event): void
    {
        $this->cache->invalidate($event->userId);
    }
}
