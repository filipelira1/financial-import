<?php

namespace App\Domain\Import\Events;

use App\Domain\Import\ImportStatus;

final readonly class ImportFinished
{
    public function __construct(
        public int $userId,
        public int $importId,
        public ImportStatus $status,
    ) {}
}
