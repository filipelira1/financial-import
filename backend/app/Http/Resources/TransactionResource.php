<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_on' => $this->occurred_on->format('Y-m-d'),
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'type' => $this->type->value,
            'import_id' => $this->import_id,
        ];
    }
}
