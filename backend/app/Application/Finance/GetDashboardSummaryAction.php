<?php

namespace App\Application\Finance;

use App\Domain\Finance\Contracts\TransactionRepository;
use App\Domain\Finance\FinancialSummary;
use App\Infrastructure\Cache\DashboardSummaryCache;

final readonly class GetDashboardSummaryAction
{
    public function __construct(
        private TransactionRepository $transactions,
        private DashboardSummaryCache $cache,
    ) {}

    public function execute(int $userId): FinancialSummary
    {
        return $this->cache->remember($userId, fn () => $this->transactions->summaryFor($userId));
    }
}
