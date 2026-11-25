<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Finance\Contracts\TransactionRepository;
use App\Domain\Finance\FinancialSummary;
use App\Domain\Finance\TransactionType;
use Illuminate\Database\ConnectionInterface;

final readonly class DbTransactionRepository implements TransactionRepository
{
    /** Keeps each statement well below the 65,535 placeholders MySQL accepts (9 columns per row). */
    private const int ROWS_PER_STATEMENT = 500;

    public function __construct(private ConnectionInterface $db) {}

    public function insertImported(int $userId, int $importId, array $transactionsByLine): int
    {
        $now = now();
        $rows = [];

        foreach ($transactionsByLine as $line => $transaction) {
            $rows[] = [
                'user_id' => $userId,
                'import_id' => $importId,
                'line_number' => $line,
                'occurred_on' => $transaction->occurredOn->format('Y-m-d'),
                'description' => $transaction->description,
                'amount_cents' => $transaction->amountCents,
                'type' => $transaction->type->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = 0;

        foreach (array_chunk($rows, self::ROWS_PER_STATEMENT) as $batch) {
            // ON DUPLICATE KEY UPDATE with an unchanged value is a no-op that MySQL reports
            // as 0 affected rows, so the sum counts only lines that were not stored yet.
            // INSERT IGNORE would also turn real errors (truncation, CHECK, foreign key) into warnings.
            // MySQL-specific: it ignores $uniqueBy and fires on ANY unique index, which is safe only
            // because (import_id, line_number) is the table's single unique key. PostgreSQL's
            // ON CONFLICT DO UPDATE would count the no-op row as affected (use DO NOTHING there).
            $inserted += $this->db->table('transactions')->upsert($batch, ['import_id', 'line_number'], ['import_id']);
        }

        return $inserted;
    }

    public function summaryFor(int $userId): FinancialSummary
    {
        // One pass over the covering index (user_id, type, amount_cents): EXPLAIN shows "Using index".
        $row = $this->db->table('transactions')
            ->where('user_id', $userId)
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END), 0) AS income', [TransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END), 0) AS expense', [TransactionType::Expense->value])
            ->selectRaw('COUNT(*) AS total')
            ->first();

        return new FinancialSummary((int) $row->income, (int) $row->expense, (int) $row->total);
    }

    public function monthlySummaryFor(int $userId): array
    {
        // One grouped pass over the user's rows (about 15,000 in the sample file: a few ms).
        // Served from the dashboard cache, so it runs once per import, not once per page view.
        $rows = $this->db->table('transactions')
            ->where('user_id', $userId)
            ->selectRaw("DATE_FORMAT(occurred_on, '%Y-%m') AS month")
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END), 0) AS income', [TransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END), 0) AS expense', [TransactionType::Expense->value])
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = [];

        foreach ($rows as $row) {
            $months[(string) $row->month] = new FinancialSummary((int) $row->income, (int) $row->expense, (int) $row->total);
        }

        return $months;
    }
}
