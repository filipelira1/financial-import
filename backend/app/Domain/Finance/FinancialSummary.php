<?php

namespace App\Domain\Finance;

use InvalidArgumentException;

/**
 * Totals of a set of transactions. The balance rule lives here and only here:
 * income adds, expense subtracts, and an expense is never counted as income.
 */
final readonly class FinancialSummary
{
    public function __construct(
        public int $incomeCents,
        public int $expenseCents,
        public int $transactionCount,
    ) {
        if ($incomeCents < 0 || $expenseCents < 0 || $transactionCount < 0) {
            throw new InvalidArgumentException('Totals cannot be negative; the sign comes from the type.');
        }
    }

    public static function empty(): self
    {
        return new self(0, 0, 0);
    }

    public function balanceCents(): int
    {
        return $this->incomeCents - $this->expenseCents;
    }

    /**
     * @return array{income_cents: int, expense_cents: int, transaction_count: int}
     */
    public function toArray(): array
    {
        return [
            'income_cents' => $this->incomeCents,
            'expense_cents' => $this->expenseCents,
            'transaction_count' => $this->transactionCount,
        ];
    }

    /**
     * @param  array{income_cents: int, expense_cents: int, transaction_count: int}  $totals
     */
    public static function fromArray(array $totals): self
    {
        return new self($totals['income_cents'], $totals['expense_cents'], $totals['transaction_count']);
    }
}
