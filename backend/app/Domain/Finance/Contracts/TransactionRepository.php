<?php

namespace App\Domain\Finance\Contracts;

use App\Domain\Finance\TransactionData;

interface TransactionRepository
{
    /**
     * Store transactions read from an import, keyed by their line in the file.
     * Lines already stored for the same import are left untouched, so a chunk
     * that runs twice does not duplicate anything.
     *
     * @param  array<int, TransactionData>  $transactionsByLine
     * @return int how many rows were actually inserted
     */
    public function insertImported(int $userId, int $importId, array $transactionsByLine): int;
}
