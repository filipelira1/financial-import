<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function insertRawTransaction(array $overrides): void
{
    DB::table('transactions')->insert(array_merge([
        'user_id' => User::factory()->create()->id,
        'occurred_on' => '2026-01-10',
        'description' => 'Office rent',
        'amount_cents' => 10_000,
        'type' => 'expense',
    ], $overrides));
}

it('accepts only income and expense as the type', function () {
    insertRawTransaction(['type' => 'transfer']);
})->throws(QueryException::class, 'transactions_type_check');

it('accepts only positive amounts', function () {
    insertRawTransaction(['amount_cents' => 0]);
})->throws(QueryException::class, 'transactions_amount_check');
