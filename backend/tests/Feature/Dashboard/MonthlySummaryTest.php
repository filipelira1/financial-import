<?php

use App\Infrastructure\Cache\DashboardSummaryCache;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('requires a token', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/dashboard/monthly')->assertUnauthorized();
});

it('totals income and expense per month, oldest first, only for the user', function () {
    Transaction::factory()->for($this->user)->income(1000)->create(['occurred_on' => '2026-02-10']);
    Transaction::factory()->for($this->user)->expense(300)->create(['occurred_on' => '2026-02-28']);
    Transaction::factory()->for($this->user)->expense(500)->create(['occurred_on' => '2025-12-31']);
    Transaction::factory()->income(9999)->create(['occurred_on' => '2026-02-10']); // someone else's

    $this->getJson('/api/v1/dashboard/monthly')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['month' => '2025-12', 'income_cents' => 0, 'expense_cents' => 500, 'balance_cents' => -500, 'transaction_count' => 1],
            ['month' => '2026-02', 'income_cents' => 1000, 'expense_cents' => 300, 'balance_cents' => 700, 'transaction_count' => 2],
        ]]);
});

it('answers an empty list when there is nothing yet', function () {
    $this->getJson('/api/v1/dashboard/monthly')->assertExactJson(['data' => []]);
});

it('is cached and refreshed by the same invalidation as the summary', function () {
    Transaction::factory()->for($this->user)->income(1000)->create(['occurred_on' => '2026-02-10']);
    $this->getJson('/api/v1/dashboard/monthly')->assertJsonCount(1, 'data');

    // Written behind the model's back, so nothing invalidates the cache: the old answer stays.
    DB::table('transactions')->insert([
        'user_id' => $this->user->id, 'occurred_on' => '2026-03-10', 'description' => 'Late', 'amount_cents' => 500, 'type' => 'income',
    ]);
    $this->getJson('/api/v1/dashboard/monthly')->assertJsonCount(1, 'data');

    app(DashboardSummaryCache::class)->invalidate($this->user->id);
    $this->getJson('/api/v1/dashboard/monthly')->assertJsonCount(2, 'data');
});
