<?php

use App\Domain\Finance\TransactionType;
use App\Domain\Import\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Transaction;

/*
 * Acceptance test with the sample file (15,000 rows).
 * Expected totals were computed independently from the CSV, in cents.
 */
it('imports the sample file with the expected totals', function () {
    $import = importFromCsv(file_get_contents(base_path('tests/fixtures/financial_transactions.csv')));

    ProcessImportJob::dispatch($import->id);

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total_rows)->toBe(15_000)
        ->and($import->imported_rows)->toBe(15_000)
        ->and($import->failed_rows)->toBe(0);

    $totals = Transaction::query()
        ->where('user_id', $import->user_id)
        ->selectRaw('SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END) AS income', [TransactionType::Income->value])
        ->selectRaw('SUM(CASE WHEN type = ? THEN amount_cents ELSE 0 END) AS expense', [TransactionType::Expense->value])
        ->selectRaw('SUM(type = ?) AS income_count', [TransactionType::Income->value])
        ->first();

    expect((int) $totals->income)->toBe(3_611_960_974)
        ->and((int) $totals->expense)->toBe(2_670_954_574)
        ->and((int) $totals->income - (int) $totals->expense)->toBe(941_006_400)
        ->and((int) $totals->income_count)->toBe(4_509);
});
