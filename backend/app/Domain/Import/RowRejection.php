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

    public static function columnCount(int $expected, int $actual, bool $spansLines = false): self
    {
        return new self([sprintf(
            'row: expected %d columns, found %d%s',
            $expected,
            $actual,
            $spansLines ? ' (a quoted value runs over several lines: check for a missing closing quote)' : '',
        )]);
    }
}
