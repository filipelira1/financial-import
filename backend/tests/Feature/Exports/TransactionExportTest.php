<?php

use App\Application\Finance\ExportTransactionsAction;
use App\Application\Finance\TransactionFilters;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('requires a token', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/transactions/export')->assertUnauthorized();
});

it('exports in the import format, newest first, only the user\'s rows', function () {
    Transaction::factory()->for($this->user)->income(500000)->create(['occurred_on' => '2026-01-05', 'description' => 'Salary']);
    Transaction::factory()->for($this->user)->expense(150000)->create(['occurred_on' => '2026-01-06', 'description' => 'Rent, January']);
    Transaction::factory()->create(); // someone else's

    $response = $this->get('/api/v1/transactions/export')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment; filename=transactions-')
        ->and(csvLines($response->streamedContent()))->toBe([
            'date,description,amount,type',
            '2026-01-06,"Rent, January",150000,Despesa',
            '2026-01-05,Salary,500000,Receita',
        ]);
});

it('uses ";" for Portuguese, as a Brazilian spreadsheet expects', function () {
    Transaction::factory()->for($this->user)->income(100)->create(['occurred_on' => '2026-01-05', 'description' => 'Salary']);

    $body = $this->get('/api/v1/transactions/export', ['Accept-Language' => 'pt-BR'])->streamedContent();

    expect(csvLines($body))->toBe(['date;description;amount;type', '2026-01-05;Salary;100;Receita']);
});

it('applies the same filters and search as the list', function () {
    Transaction::factory()->for($this->user)->expense(100)->create(['occurred_on' => '2026-01-10', 'description' => 'Netflix']);
    Transaction::factory()->for($this->user)->expense(200)->create(['occurred_on' => '2026-02-10', 'description' => 'Netflix']);
    Transaction::factory()->for($this->user)->income(300)->create(['occurred_on' => '2026-01-11', 'description' => 'Netflix refund']);
    Transaction::factory()->for($this->user)->expense(400)->create(['occurred_on' => '2026-01-12', 'description' => 'Rent']);

    $body = $this->get('/api/v1/transactions/export?type=expense&from=2026-01-01&to=2026-01-31&q=netflix')->streamedContent();

    expect(csvLines($body))->toBe(['date,description,amount,type', '2026-01-10,Netflix,100,Despesa']);
});

it('neutralises values a spreadsheet would run as a formula', function () {
    Transaction::factory()->for($this->user)->expense(100)->create(['description' => '=HYPERLINK("http://evil","x")']);

    $body = $this->get('/api/v1/transactions/export')->streamedContent();

    expect(csvLines($body)[1])->toContain('"\'=HYPERLINK(""http://evil"",""x"")"');
});

it('pages by key without skipping or repeating rows, even across a same-day boundary', function () {
    // 7 rows on the same day and 3 on another, read 3 at a time: the pages split inside the same day.
    Transaction::factory()->for($this->user)->count(7)->create(['occurred_on' => '2026-03-01']);
    Transaction::factory()->for($this->user)->count(3)->create(['occurred_on' => '2026-02-01']);

    $ids = collect((new ExportTransactionsAction(pageSize: 3))->execute($this->user->id, new TransactionFilters))
        ->pluck('id')->all();

    $expected = Transaction::query()->orderByDesc('occurred_on')->orderByDesc('id')->pluck('id')->all();
    expect($ids)->toBe($expected)->toHaveCount(10);
});
