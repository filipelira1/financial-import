<?php

namespace App\Domain\Import;

/**
 * Why a source row could not become a transaction.
 */
final readonly class RowRejection
{
    /**
     * @param  non-empty-list<string>  $errors
     */
    public function __construct(public array $errors) {}

    public static function columnCount(int $expected, int $actual): self
    {
        return new self([sprintf('row: expected %d columns, found %d', $expected, $actual)]);
    }
}
