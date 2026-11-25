<?php

namespace App\Application\Finance;

use App\Models\Transaction;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every transaction matching the filters, in list order, read in fixed-size pages.
 *
 * Keyset pagination on (occurred_on, id): each page starts right after the last row of the
 * previous one, so the cost per page stays flat however deep the export goes (OFFSET would
 * walk and discard all earlier rows), and memory holds one page at a time whatever the total.
 */
final readonly class ExportTransactionsAction
{
    public function __construct(private int $pageSize = 1000) {}

    /**
     * @return Generator<int, Transaction>
     */
    public function execute(int $userId, TransactionFilters $filters): Generator
    {
        $last = null;

        do {
            $page = $filters->query($userId)
                ->when($last, fn (Builder $query, Transaction $last) => $query->where(
                    fn (Builder $after) => $after
                        ->where('occurred_on', '<', $last->occurred_on->format('Y-m-d'))
                        ->orWhere(fn (Builder $sameDay) => $sameDay
                            ->where('occurred_on', $last->occurred_on->format('Y-m-d'))
                            ->where('id', '<', $last->id))
                ))
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit($this->pageSize)
                ->get();

            // Plain yield (not yield from): keys keep counting across pages instead of restarting at 0.
            foreach ($page as $transaction) {
                yield $transaction;
            }

            $last = $page->last();
        } while ($page->count() === $this->pageSize);
    }
}
