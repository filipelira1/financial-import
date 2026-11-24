<?php

namespace App\Infrastructure\Csv;

final readonly class CsvRow
{
    /**
     * @param  int  $line  line in the file where the row starts (the header is line 1)
     * @param  list<string>  $fields
     */
    public function __construct(
        public int $line,
        public array $fields,
    ) {}
}
