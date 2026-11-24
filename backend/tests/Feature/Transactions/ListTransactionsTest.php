<?php

use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('requires a token', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/transactions')->assertUnauthorized();
});

it('lists the user\'s transactions newest first, paginated', function () {
    Transaction::factory()->for($this->user)->count(25)->create();
    $sameDay = Transaction::factory()->for($this->user)->count(2)->create(['occurred_on' => '2030-01-01']);
    Transaction::factory()->count(3)->create(); // someone else's

    $response = $this->getJson('/api/v1/transactions?per_page=10&page=1')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 27)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonStructure(['data' => [['id', 'occurred_on', 'description', 'amount_cents', 'type', 'import_id']], 'links', 'meta']);

    // Same date: the higher id first, so pages never shuffle.
    expect($response->json('data.0.id'))->toBe($sameDay[1]->id)
        ->and($response->json('data.1.id'))->toBe($sameDay[0]->id);

    $dates = array_column($response->json('data'), 'occurred_on');
    expect($dates)->toBe(collect($dates)->sortDesc()->values()->all());
});

it('filters by type and period', function () {
    Transaction::factory()->for($this->user)->income(100)->create(['occurred_on' => '2026-01-10']);
    Transaction::factory()->for($this->user)->expense(200)->create(['occurred_on' => '2026-01-15']);
    Transaction::factory()->for($this->user)->expense(300)->create(['occurred_on' => '2026-02-01']);

    $this->getJson('/api/v1/transactions?type=expense')
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/transactions?from=2026-01-01&to=2026-01-31')
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/transactions?type=expense&from=2026-01-01&to=2026-01-31')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.amount_cents', 200);
});

it('keeps the filters in the pagination links', function () {
    Transaction::factory()->for($this->user)->expense(100)->count(6)->create();

    expect($this->getJson('/api/v1/transactions?type=expense&per_page=5')->json('links.next'))
        ->toContain('type=expense')->toContain('per_page=5');
});

it('validates the filters', function (string $query, string $field) {
    $this->getJson("/api/v1/transactions?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    ['type=transfer', 'type'],
    ['from=01/02/2026', 'from'],
    ['from=2026-02-01&to=2026-01-01', 'to'],
    ['per_page=1000', 'per_page'],
]);
