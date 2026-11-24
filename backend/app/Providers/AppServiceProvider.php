<?php

namespace App\Providers;

use App\Domain\Finance\Contracts\TransactionRepository;
use App\Infrastructure\Persistence\DbTransactionRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        TransactionRepository::class => DbTransactionRepository::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fail loudly in development on N+1 queries and silently dropped attributes.
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Per account (stops guessing one password) and per IP (stops spraying many accounts).
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('email:'.Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
