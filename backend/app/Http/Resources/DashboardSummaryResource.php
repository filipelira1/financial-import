<?php

namespace App\Http\Resources;

use App\Domain\Finance\FinancialSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property FinancialSummary $resource */
class DashboardSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => 'BRL',
            'income_cents' => $this->resource->incomeCents,
            'expense_cents' => $this->resource->expenseCents,
            'balance_cents' => $this->resource->balanceCents(),
            'transaction_count' => $this->resource->transactionCount,
        ];
    }
}
