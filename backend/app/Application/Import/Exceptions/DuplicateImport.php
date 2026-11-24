<?php

namespace App\Application\Import\Exceptions;

use App\Models\Import;
use RuntimeException;

final class DuplicateImport extends RuntimeException
{
    public function __construct(public readonly Import $existing)
    {
        parent::__construct(sprintf(
            'This file was already uploaded (import #%d, %s).',
            $existing->id,
            $existing->status->value,
        ));
    }
}
