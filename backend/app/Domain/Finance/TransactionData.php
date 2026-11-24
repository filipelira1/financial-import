<?php

namespace App\Domain\Finance;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A validated financial transaction, independent of where it came from.
 * Amounts are always positive cents; whether they add or subtract is the type's job.
 */
final readonly class TransactionData
{
    public function __construct(
        public DateTimeImmutable $occurredOn,
        public string $description,
        public int $amountCents,
        public TransactionType $type,
    ) {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('The amount must be a positive number of cents.');
        }

        if (trim($description) === '') {
            throw new InvalidArgumentException('The description is required.');
        }
    }
}
