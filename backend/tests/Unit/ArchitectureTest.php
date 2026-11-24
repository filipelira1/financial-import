<?php

use Illuminate\Contracts\Queue\ShouldQueue;

arch('the domain is plain PHP: no framework, database or HTTP')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'App\Models', 'App\Http', 'App\Jobs', 'App\Infrastructure', 'App\Application']);

arch('domain events and data objects are immutable')
    ->expect([
        'App\Domain\Finance\TransactionData',
        'App\Domain\Finance\Events',
        'App\Domain\Import\Events',
        'App\Domain\Import\RowRejection',
    ])
    ->toBeReadonly()
    ->toBeFinal();

arch('use cases do not depend on the HTTP layer')
    ->expect('App\Application')
    ->not->toUse(['App\Http', 'Illuminate\Http\Request', 'Illuminate\Http\JsonResponse']);

arch('controllers stay thin: no queries, queues or infrastructure')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Bus',
        'Illuminate\Support\Facades\Queue',
        'Illuminate\Support\Facades\Cache',
        'App\Infrastructure',
    ]);

arch('jobs are queued')
    ->expect('App\Jobs')
    ->toImplement(ShouldQueue::class);

arch('no debugging leftovers')
    ->preset()->php();

arch('no insecure functions')
    ->preset()->security();
