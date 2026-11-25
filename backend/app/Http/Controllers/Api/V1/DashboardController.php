<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Finance\GetDashboardSummaryAction;
use App\Application\Finance\GetMonthlySummaryAction;
use App\Domain\Finance\FinancialSummary;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Total income, total expense and balance of the user's transactions, in cents.
     * Served from cache; refreshed whenever new transactions are stored.
     */
    public function summary(Request $request, GetDashboardSummaryAction $summary): DashboardSummaryResource
    {
        return new DashboardSummaryResource($summary->execute($request->user()->id));
    }

    /**
     * Income, expense and balance per calendar month, oldest first, in cents.
     * Cached together with the summary and refreshed by the same imports.
     *
     * @response array{data: list<array{month: string, income_cents: int, expense_cents: int, balance_cents: int, transaction_count: int}>}
     */
    public function monthly(Request $request, GetMonthlySummaryAction $monthly): JsonResponse
    {
        $months = [];

        foreach ($monthly->execute($request->user()->id) as $month => $summary) {
            $months[] = ['month' => $month, ...$this->totals($summary)];
        }

        return response()->json(['data' => $months]);
    }

    /**
     * @return array{income_cents: int, expense_cents: int, balance_cents: int, transaction_count: int}
     */
    private function totals(FinancialSummary $summary): array
    {
        return [
            'income_cents' => $summary->incomeCents,
            'expense_cents' => $summary->expenseCents,
            'balance_cents' => $summary->balanceCents(),
            'transaction_count' => $summary->transactionCount,
        ];
    }
}
