<?php

namespace App\Http\Resources;

use App\Models\ImportFailure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ImportFailure */
class ImportFailureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'line_number' => $this->line_number,
            'raw' => $this->raw,
            'errors' => $this->errors,
        ];
    }
}
