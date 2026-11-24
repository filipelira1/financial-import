<?php

use App\Application\Finance\GetDashboardSummaryAction;
use App\Domain\Finance\Events\TransactionsImported;
use App\Domain\Finance\FinancialSummary;
use App\Domain\Import\Events\ImportFinished;
use App\Domain\Import\ImportStatus;
use App\Infrastructure\Cache\DashboardSummaryCache;
use App\Jobs\ProcessImportJob;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * These tests run on the real Redis store, not the in-memory array store: the array store
 * keeps PHP objects as they are and would hide a serialization problem (Laravel refuses to
 * unserialize objects from the cache). Redis database 15 is reserved for tests.
 */
beforeEach(function () {
    config(['cache.default' => 'redis']);
    app()->forgetInstance('cache.store');
    Cache::flush();

    $this->user = User::factory()->create();
});

it('runs on the real Redis cache store', function () {
    expect(app(CacheRepository::class)->getStore())->toBeInstanceOf(RedisStore::class)
        ->and(app(DashboardSummaryCache::class))->toBeInstanceOf(DashboardSummaryCache::class);

    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/dashboard/summary')->assertOk();

    // What Redis holds is a plain array, rebuilt into the value object on the way out.
    expect(Cache::get("dashboard:summary:{$this->user->id}:v0"))->toBe([
        'income_cents' => 0, 'expense_cents' => 0, 'transaction_count' => 0,
    ]);
});

function summaryQueries(): int
{
    return collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from `transactions`'))->count();
}

it('requires a token', function () {
    $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
});

it('returns income, expense and balance in cents', function () {
    Transaction::factory()->for($this->user)->income(100_000)->create();
    Transaction::factory()->for($this->user)->income(50_000)->create();
    Transaction::factory()->for($this->user)->expense(30_000)->create();
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertExactJson(['data' => [
            'currency' => 'BRL',
            'income_cents' => 150_000,
            'expense_cents' => 30_000,
            'balance_cents' => 120_000,
            'transaction_count' => 3,
        ]]);
});

it('never counts an expense as income', function () {
    Transaction::factory()->for($this->user)->expense(40_000)->count(2)->create();
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/dashboard/summary')
        ->assertJsonPath('data.income_cents', 0)
        ->assertJsonPath('data.expense_cents', 80_000)
        ->assertJsonPath('data.balance_cents', -80_000);
});

it('only adds up the user\'s own transactions', function () {
    Transaction::factory()->for($this->user)->income(1_000)->create();
    Transaction::factory()->income(999_999)->create();
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 1_000);
});

it('serves repeated reads from the cache', function () {
    Transaction::factory()->for($this->user)->income(1_000)->create();
    Sanctum::actingAs($this->user);
    DB::enableQueryLog();

    $this->getJson('/api/v1/dashboard/summary')->assertOk();
    $this->getJson('/api/v1/dashboard/summary')->assertOk();
    $this->getJson('/api/v1/dashboard/summary')->assertOk();

    expect(summaryQueries())->toBe(1);
});

it('is refreshed when an import stores new transactions', function () {
    Transaction::factory()->for($this->user)->income(1_000)->create();
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 1_000);

    // Bulk inserts bypass Eloquent (no observer): the import's domain event must invalidate.
    DB::table('transactions')->insert([
        'user_id' => $this->user->id, 'occurred_on' => '2026-01-01', 'description' => 'Bulk',
        'amount_cents' => 5_000, 'type' => 'income',
    ]);
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 1_000); // still cached

    event(new TransactionsImported($this->user->id, 1, 1));

    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 6_000);
});

it('is refreshed when an import finishes, even if a chunk event was lost', function () {
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 0);

    // A worker died after committing a chunk but before announcing it; the retried chunk
    // inserted nothing new, so no TransactionsImported event was ever dispatched.
    Transaction::factory()->for($this->user)->income(9_000)->createQuietly();
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 0);

    event(new ImportFinished($this->user->id, 1, ImportStatus::Completed));

    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 9_000);
});

it('is refreshed when a single transaction is saved or deleted', function () {
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 0);

    $transaction = Transaction::factory()->for($this->user)->income(7_000)->create();
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 7_000);

    $transaction->delete();
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 0);
});

it('does not let a slow read put stale totals back after an invalidation', function () {
    $cache = app(DashboardSummaryCache::class);

    // A read starts computing old totals; meanwhile an import commits and invalidates.
    $stale = $cache->remember($this->user->id, function () use ($cache) {
        $cache->invalidate($this->user->id);

        return new FinancialSummary(1, 0, 1);
    });

    $fresh = $cache->remember($this->user->id, fn () => new FinancialSummary(2, 0, 2));

    expect($stale->incomeCents)->toBe(1)
        ->and($fresh->incomeCents)->toBe(2);
});

it('matches the imported file end to end', function () {
    config(['imports.chunk_size' => 3]);
    $import = importFromCsv(dirtyCsv(), $this->user);
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.transaction_count', 0); // warm the cache

    ProcessImportJob::dispatch($import->id);

    $this->getJson('/api/v1/dashboard/summary')
        ->assertJsonPath('data.income_cents', 525_000)
        ->assertJsonPath('data.expense_cents', 190_000)
        ->assertJsonPath('data.balance_cents', 335_000)
        ->assertJsonPath('data.transaction_count', 4);
});

it('computes the totals in a single query', function () {
    Transaction::factory()->for($this->user)->count(5)->create();
    DB::enableQueryLog();

    app(GetDashboardSummaryAction::class)->execute($this->user->id);

    expect(summaryQueries())->toBe(1);
});
