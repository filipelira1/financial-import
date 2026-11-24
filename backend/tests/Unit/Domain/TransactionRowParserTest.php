<?php

use App\Domain\Finance\TransactionData;
use App\Domain\Finance\TransactionType;
use App\Domain\Import\RowRejection;
use App\Domain\Import\TransactionRowParser;

function row(array $overrides = []): array
{
    return array_merge([
        'date' => '2026-05-01',
        'description' => 'Mensalidade Cliente B #869',
        'amount' => '115346',
        'type' => 'Receita',
    ], $overrides);
}

it('maps a valid row to a transaction in cents', function () {
    $result = (new TransactionRowParser)->parse(row());

    expect($result)->toBeInstanceOf(TransactionData::class)
        ->and($result->occurredOn->format('Y-m-d'))->toBe('2026-05-01')
        ->and($result->description)->toBe('Mensalidade Cliente B #869')
        ->and($result->amountCents)->toBe(115346)
        ->and($result->type)->toBe(TransactionType::Income);
});

it('maps Despesa to expense, never to income', function (string $label) {
    expect((new TransactionRowParser)->parse(row(['type' => $label]))->type)
        ->toBe(TransactionType::Expense);
})->with(['Despesa', 'despesa', ' DESPESA ', 'expense']);

it('maps Receita to income', function (string $label) {
    expect((new TransactionRowParser)->parse(row(['type' => $label]))->type)
        ->toBe(TransactionType::Income);
})->with(['Receita', 'receita', 'RECEITA', 'income']);

it('trims surrounding whitespace', function () {
    $result = (new TransactionRowParser)->parse(row([
        'date' => ' 2026-05-01 ', 'description' => '  Rent  ', 'amount' => ' 500 ',
    ]));

    expect($result->description)->toBe('Rent')->and($result->amountCents)->toBe(500);
});

it('rejects an invalid value with the reason', function (array $values, string $reason) {
    $result = (new TransactionRowParser)->parse(row($values));

    expect($result)->toBeInstanceOf(RowRejection::class)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0])->toStartWith($reason);
})->with([
    'impossible date' => [['date' => '2025-02-30'], 'date:'],
    'month 13' => [['date' => '2025-13-01'], 'date:'],
    'brazilian date' => [['date' => '01/05/2026'], 'date:'],
    'date without padding' => [['date' => '2026-5-1'], 'date:'],
    'empty date' => [['date' => ''], 'date:'],
    'empty description' => [['description' => '   '], 'description:'],
    'long description' => [['description' => str_repeat('a', 256)], 'description:'],
    'negative amount' => [['amount' => '-500'], 'amount:'],
    'decimal comma' => [['amount' => '1234,56'], 'amount:'],
    'thousand separator' => [['amount' => '1.234'], 'amount:'],
    'decimal point' => [['amount' => '12.5'], 'amount:'],
    'zero' => [['amount' => '0'], 'amount:'],
    'explicit plus sign' => [['amount' => '+500'], 'amount:'],
    'too large' => [['amount' => str_repeat('9', 19)], 'amount:'],
    'unknown type' => [['type' => 'Transferencia'], 'type:'],
    'empty type' => [['type' => ''], 'type:'],
]);

it('reports every problem of a row at once', function () {
    $result = (new TransactionRowParser)->parse(['date' => 'x', 'description' => '', 'amount' => '-1', 'type' => 'y']);

    expect($result->errors)->toHaveCount(4);
});

it('treats a missing column as empty', function () {
    $result = (new TransactionRowParser)->parse(['date' => '2026-05-01', 'description' => 'Rent', 'amount' => '500']);

    expect($result)->toBeInstanceOf(RowRejection::class)
        ->and($result->errors[0])->toStartWith('type:');
});

it('refuses to build a transaction with a non-positive amount', function () {
    new TransactionData(new DateTimeImmutable, 'Rent', 0, TransactionType::Expense);
})->throws(InvalidArgumentException::class);
