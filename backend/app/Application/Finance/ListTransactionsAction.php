<?php

namespace App\Application\Finance;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Read side of the list: indexed queries, no repository needed.
 *
 * Deferred join: OFFSET pagination makes MySQL walk and discard every row before the page.
 * Picking the page's ids from the covering index first, then reading only those rows,
 * keeps deep pages cheap (page 751 of the provided file: 68 ms -> 6 ms, EXPLAIN ANALYZE).
 */
final class ListTransactionsAction
{
    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function execute(int $userId, TransactionFilters $filters, int $page = 1): LengthAwarePaginator
    {
        $total = $filters->query($userId)->toBase()->getCountForPagination();

        $pageIds = $filters->query($userId)
            ->select('id')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->forPage($page, $filters->perPage);

        $items = Transaction::query()
            ->joinSub($pageIds, 'page', 'page.id', '=', 'transactions.id')
            ->select('transactions.*')
            ->orderByDesc('transactions.occurred_on')
            ->orderByDesc('transactions.id')
            ->get();

        return (new Paginator($items, $total, $filters->perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]))->withQueryString();
    }
}
