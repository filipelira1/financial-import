<?php

namespace App\Domain\Finance;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    /** How the type is written in the CSV files the system reads and writes. */
    public function csvLabel(): string
    {
        return match ($this) {
            self::Income => 'Receita',
            self::Expense => 'Despesa',
        };
    }
}
