<?php

namespace App\Application\Finance;

use App\Domain\Finance\TransactionType;
use App\Models\Transaction;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class TransactionFilters
{
    public function __construct(
        public ?TransactionType $type = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public int $perPage = 20,
        public ?string $search = null,
    ) {}

    /**
     * The user's transactions matching these filters (shared by the list and the export).
     *
     * @return Builder<Transaction>
     */
    public function query(int $userId): Builder
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->when($this->type, fn (Builder $query, $type) => $query->where('type', $type))
            ->when($this->from, fn (Builder $query, $from) => $query->where('occurred_on', '>=', $from->format('Y-m-d')))
            ->when($this->to, fn (Builder $query, $to) => $query->where('occurred_on', '<=', $to->format('Y-m-d')))
            // "Contains" search: a B-tree index cannot serve a leading wildcard, so MySQL scans the
            // user's rows (already narrowed by the user_id indexes). Fine for one account's history;
            // a FULLTEXT index would be the next step for millions of rows per user.
            // % and _ typed by the user are matched literally, not as wildcards.
            ->when($this->search, fn (Builder $query, $search) => $query->where(
                'description', 'like', '%'.addcslashes($search, '\\%_').'%'
            ));
    }
}
