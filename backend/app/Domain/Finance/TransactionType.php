<?php

namespace App\Domain\Finance;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
