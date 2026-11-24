<?php

use App\Domain\Finance\FinancialSummary;

it('subtracts expenses from income', function () {
    expect((new FinancialSummary(150_000, 30_000, 3))->balanceCents())->toBe(120_000);
});

it('allows a negative balance', function () {
    expect((new FinancialSummary(10_000, 25_000, 2))->balanceCents())->toBe(-15_000);
});

it('starts empty', function () {
    expect(FinancialSummary::empty()->balanceCents())->toBe(0);
});

it('round-trips through a plain array for the cache', function () {
    $summary = new FinancialSummary(1, 2, 3);

    expect(FinancialSummary::fromArray($summary->toArray()))->toEqual($summary);
});

it('refuses negative totals: the sign comes from the type', function () {
    new FinancialSummary(-1, 0, 1);
})->throws(InvalidArgumentException::class);
