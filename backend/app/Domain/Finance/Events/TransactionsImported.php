<?php

namespace App\Domain\Finance\Events;

/**
 * New transactions were committed for a user. Anything derived from the user's
 * transactions (such as the dashboard totals) is now stale.
 */
final readonly class TransactionsImported
{
    public function __construct(
        public int $userId,
        public int $importId,
        public int $count,
    ) {}
}
