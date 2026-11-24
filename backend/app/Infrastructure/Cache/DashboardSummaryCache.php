<?php

namespace App\Infrastructure\Cache;

use App\Domain\Finance\FinancialSummary;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Per-user cache of the dashboard totals, invalidated by bumping a version number.
 *
 * Deleting the key would leave a race: a read that started before an import committed
 * can write the old totals back right after the delete. With a versioned key that late
 * write lands under a version nobody reads any more, and the next read recomputes.
 */
final readonly class DashboardSummaryCache
{
    /** Safety net: even without an invalidation, totals are recomputed every hour. */
    private const int TTL_SECONDS = 3600;

    public function __construct(private Cache $cache) {}

    /**
     * @param  Closure(): FinancialSummary  $compute
     */
    public function remember(int $userId, Closure $compute): FinancialSummary
    {
        $key = sprintf('dashboard:summary:%d:v%d', $userId, $this->version($userId));

        // Plain arrays only: the cache refuses to unserialize objects (gadget-chain protection).
        return FinancialSummary::fromArray(
            $this->cache->remember($key, self::TTL_SECONDS, fn () => $compute()->toArray())
        );
    }

    public function invalidate(int $userId): void
    {
        $this->cache->increment($this->versionKey($userId));
    }

    private function version(int $userId): int
    {
        return (int) $this->cache->get($this->versionKey($userId), 0);
    }

    private function versionKey(int $userId): string
    {
        return "dashboard:summary:{$userId}:version";
    }
}
