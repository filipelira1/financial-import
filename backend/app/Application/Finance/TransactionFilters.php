<?php

namespace App\Application\Finance;

use App\Domain\Finance\TransactionType;
use DateTimeImmutable;

final readonly class TransactionFilters
{
    public function __construct(
        public ?TransactionType $type = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public int $perPage = 20,
    ) {}
}
