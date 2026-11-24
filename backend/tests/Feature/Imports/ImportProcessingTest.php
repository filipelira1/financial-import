<?php

use App\Application\Import\FinalizeImportAction;
use App\Application\Import\ImportChunkAction;
use App\Application\Import\PlanImportAction;
use App\Application\Import\RetryImportAction;
use App\Domain\Finance\Events\TransactionsImported;
use App\Domain\Finance\TransactionType;
use App\Domain\Import\Events\ImportFinished;
use App\Domain\Import\ImportStatus;
use App\Jobs\ImportChunkJob;
use App\Jobs\ProcessImportJob;
use App\Models\ImportFailure;
use App\Models\Transaction;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['imports.chunk_size' => 3]);
});

it('imports the valid rows and keeps every rejected line with its reason', function () {
    $import = importFromCsv(dirtyCsv());

    ProcessImportJob::dispatch($import->id);

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and($import->total_rows)->toBe(11)
        ->and($import->imported_rows)->toBe(4)
        ->and($import->failed_rows)->toBe(7)
        ->and($import->finished_at)->not->toBeNull()
        ->and(Transaction::query()->orderBy('line_number')->pluck('line_number')->all())->toBe([2, 3, 12, 13]);

    $reasons = ImportFailure::query()->orderBy('line_number')->get()
        ->mapWithKeys(fn (ImportFailure $failure) => [$failure->line_number => $failure->errors[0]])
        ->all();

    expect(array_keys($reasons))->toBe([4, 5, 6, 7, 8, 9, 11])
        ->and($reasons[4])->toStartWith('type:')
        ->and($reasons[5])->toStartWith('date:')
        ->and($reasons[6])->toStartWith('amount:')
        ->and($reasons[7])->toStartWith('amount:')
        ->and($reasons[8])->toBe('row: expected 4 columns, found 5')
        ->and($reasons[9])->toBe('row: expected 4 columns, found 3')
        ->and($reasons[11])->toStartWith('description:');
});

it('never counts an expense as income', function () {
    $import = importFromCsv(dirtyCsv());

    ProcessImportJob::dispatch($import->id);

    $sums = Transaction::query()->selectRaw('type, SUM(amount_cents) AS total')->groupBy('type')->pluck('total', 'type');

    expect((int) $sums[TransactionType::Income->value])->toBe(525_000)
        ->and((int) $sums[TransactionType::Expense->value])->toBe(190_000)
        ->and(Transaction::query()->where('description', 'Taxes')->value('type'))->toBe(TransactionType::Expense);
});

it('fans the file out as a batch with one small job per chunk', function () {
    Bus::fake();
    $import = importFromCsv(dirtyCsv());

    (new ProcessImportJob($import->id))->handle(app(PlanImportAction::class), app(FinalizeImportAction::class));

    Bus::assertBatched(function (PendingBatch $batch) use ($import) {
        $rows = $batch->jobs->map(fn (ImportChunkJob $job) => $job->chunk->rows)->all();

        return $batch->name === "import-{$import->id}"
            && $batch->queue() === 'imports'
            && $rows === [3, 3, 3, 2];
    });

    expect($import->refresh()->status)->toBe(ImportStatus::Processing)
        ->and($import->total_rows)->toBe(11)
        ->and($import->batch_id)->not->toBeNull();
});

it('does not duplicate anything when a chunk runs twice', function () {
    $import = importFromCsv(dirtyCsv());
    $chunks = app(PlanImportAction::class)->execute($import);
    $action = app(ImportChunkAction::class);

    $action->execute($import->id, $chunks[0]);
    $action->execute($import->id, $chunks[0]); // e.g. the worker died after the commit

    expect(Transaction::count())->toBe(2)
        ->and(ImportFailure::count())->toBe(1)
        ->and($import->refresh()->imported_rows)->toBe(2)
        ->and($import->failed_rows)->toBe(1);
});

it('announces new transactions only after they are committed, once per chunk that stored any', function () {
    Event::fake([TransactionsImported::class, ImportFinished::class]);
    $import = importFromCsv(dirtyCsv());

    ProcessImportJob::dispatch($import->id);

    // Chunks: lines 2-4 (2 valid), 5-7 (0), 8-11 (0), 12-13 (2).
    Event::assertDispatchedTimes(TransactionsImported::class, 2);
    Event::assertDispatched(fn (TransactionsImported $event) => $event->userId === $import->user_id && $event->count === 2);
    Event::assertDispatched(fn (ImportFinished $event) => $event->status === ImportStatus::CompletedWithErrors);
});

it('fails without retrying when the file has no usable header', function () {
    $import = importFromCsv("date,description,value\n2026-01-05,Salary,500000\n");

    ProcessImportJob::dispatch($import->id);

    expect($import->refresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->failure_reason)->toContain('Missing required column(s): amount, type')
        ->and(Transaction::count())->toBe(0);
});

it('completes an import whose file has only a header', function () {
    $import = importFromCsv("date,description,amount,type\n");

    ProcessImportJob::dispatch($import->id);

    expect($import->refresh()->status)->toBe(ImportStatus::Completed)
        ->and($import->total_rows)->toBe(0);
});

it('fails the import when a chunk could not be processed', function () {
    $import = importFromCsv(dirtyCsv());
    $import->update(['total_rows' => 11]);

    $finished = app(FinalizeImportAction::class)->execute($import->id, failedChunks: 1);

    expect($finished->status)->toBe(ImportStatus::Failed)
        ->and($finished->failure_reason)->toContain('1 chunk(s) could not be processed');
});

it('fails the import when imported plus rejected rows do not add up to the file', function () {
    $import = importFromCsv(dirtyCsv());
    $chunks = app(PlanImportAction::class)->execute($import);
    app(ImportChunkAction::class)->execute($import->id, $chunks[0]);

    $finished = app(FinalizeImportAction::class)->execute($import->id);

    expect($finished->status)->toBe(ImportStatus::Failed)
        ->and($finished->failure_reason)->toBe('Row count mismatch: 2 imported + 1 rejected, but the file has 11 rows.');
});

it('finishes a failed import on retry without duplicating the rows already stored', function () {
    $import = importFromCsv(dirtyCsv());
    $chunks = app(PlanImportAction::class)->execute($import);
    app(ImportChunkAction::class)->execute($import->id, $chunks[0]);
    app(FinalizeImportAction::class)->execute($import->id, failedChunks: 3);
    expect($import->refresh()->status)->toBe(ImportStatus::Failed);

    app(RetryImportAction::class)->execute($import);

    expect($import->refresh()->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and(Transaction::count())->toBe(4)
        ->and(ImportFailure::count())->toBe(7)
        ->and($import->failure_reason)->toBeNull();
});
