<?php

namespace App\Observers;

use App\Infrastructure\Cache\DashboardSummaryCache;
use App\Models\Transaction;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Covers transactions written one at a time through Eloquent (imports use the event).
 */
final readonly class TransactionObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private DashboardSummaryCache $cache) {}

    public function saved(Transaction $transaction): void
    {
        $this->cache->invalidate($transaction->user_id);
    }

    public function deleted(Transaction $transaction): void
    {
        $this->cache->invalidate($transaction->user_id);
    }
}
