<?php

namespace App\Http\Resources;

use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Import */
class ImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $processed = $this->imported_rows + $this->failed_rows;

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'status' => $this->status->value,
            'is_finished' => $this->status->isFinished(),
            'total_rows' => $this->total_rows,
            'processed_rows' => $processed,
            'imported_rows' => $this->imported_rows,
            'failed_rows' => $this->failed_rows,
            // 0-100. Unknown until the file has been read (total_rows is null while pending).
            'progress' => $this->progress($processed),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }

    private function progress(int $processed): int
    {
        if ($this->status->isFinished()) {
            return 100;
        }

        if (! $this->total_rows) {
            return 0;
        }

        return min(99, intdiv($processed * 100, $this->total_rows));
    }
}
