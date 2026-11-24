<?php

namespace App\Console\Commands;

use App\Application\Import\DiscardFailedImportsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('imports:discard-failed {--days=7 : Grace period to retry a failed import}')]
#[Description('Discard failed imports that were not retried: file, import and partial rows')]
class DiscardFailedImports extends Command
{
    public function handle(DiscardFailedImportsAction $discard): int
    {
        $count = $discard->execute((int) $this->option('days'));
        $this->info("Discarded {$count} failed import(s).");

        return self::SUCCESS;
    }
}
