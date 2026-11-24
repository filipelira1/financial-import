<?php

namespace App\Models;

use App\Domain\Import\ImportStatus;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $original_name
 * @property string $stored_path
 * @property string $file_hash
 * @property int $file_size
 * @property ImportStatus $status
 * @property int|null $total_rows
 * @property int $imported_rows
 * @property int $failed_rows
 * @property string|null $batch_id
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'pending',
        'imported_rows' => 0,
        'failed_rows' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'failed_rows' => 'integer',
            'file_size' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<ImportFailure, $this> */
    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class);
    }
}
