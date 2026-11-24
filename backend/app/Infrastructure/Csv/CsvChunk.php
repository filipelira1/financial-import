<?php

namespace App\Infrastructure\Csv;

/**
 * Where a chunk of rows starts in the file. Small enough to travel in a queued job.
 */
final readonly class CsvChunk
{
    public function __construct(
        public int $offset,
        public int $firstLine,
        public int $rows,
    ) {}
}
