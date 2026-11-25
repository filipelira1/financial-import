<?php

namespace App\Http\Requests\Api\V1;

use App\Application\Finance\TransactionFilters;
use App\Domain\Finance\TransactionType;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTransactionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:5,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function filters(): TransactionFilters
    {
        return new TransactionFilters(
            type: $this->enum('type', TransactionType::class),
            from: $this->filled('from') ? new DateTimeImmutable($this->string('from')) : null,
            to: $this->filled('to') ? new DateTimeImmutable($this->string('to')) : null,
            perPage: $this->integer('per_page', 20),
            search: $this->filled('q') ? $this->string('q')->trim()->value() : null,
        );
    }
}
