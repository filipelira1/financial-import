<?php

use Illuminate\Support\Facades\Schedule;

// Runs in the "scheduler" container (php artisan schedule:work).

// Expired API tokens are refused already; this only keeps the table small.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();

// Imports that stopped making progress become "failed", so the user can retry them.
Schedule::command('imports:recover-stuck --minutes=60')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Failed imports not retried within a week are discarded (file, import and partial rows).
Schedule::command('imports:discard-failed --days=7')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
