<?php

namespace App\Console\Commands;

use App\Application\Import\RecoverStuckImportsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('imports:recover-stuck {--minutes=60 : Idle time after which an import counts as stuck}')]
#[Description('Fail imports that stopped making progress, so they can be retried')]
class RecoverStuckImports extends Command
{
    public function handle(RecoverStuckImportsAction $recover): int
    {
        $count = $recover->execute((int) $this->option('minutes'));
        $this->info("Recovered {$count} stuck import(s).");

        return self::SUCCESS;
    }
}
