<?php

namespace App\Application\Finance;

use App\Domain\Finance\Contracts\TransactionRepository;
use App\Domain\Finance\FinancialSummary;
use App\Infrastructure\Cache\DashboardSummaryCache;

final readonly class GetMonthlySummaryAction
{
    public function __construct(
        private TransactionRepository $transactions,
        private DashboardSummaryCache $cache,
    ) {}

    /**
     * @return array<string, FinancialSummary>
     */
    public function execute(int $userId): array
    {
        return $this->cache->rememberMonthly($userId, fn () => $this->transactions->monthlySummaryFor($userId));
    }
}
