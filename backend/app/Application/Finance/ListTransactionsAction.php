<?php

namespace App\Application\Finance;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read side of the list: a plain indexed query, no repository needed.
 */
final class ListTransactionsAction
{
    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function execute(int $userId, TransactionFilters $filters): LengthAwarePaginator
    {
        // Walks the (user_id, occurred_on, id) index backwards: no sort in memory.
        return Transaction::query()
            ->where('user_id', $userId)
            ->when($filters->type, fn ($query, $type) => $query->where('type', $type))
            ->when($filters->from, fn ($query, $from) => $query->where('occurred_on', '>=', $from->format('Y-m-d')))
            ->when($filters->to, fn ($query, $to) => $query->where('occurred_on', '<=', $to->format('Y-m-d')))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate($filters->perPage)
            ->withQueryString();
    }
}
