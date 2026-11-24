<?php

namespace Database\Factories;

use App\Domain\Import\ImportStatus;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => 'statement.csv',
            'stored_path' => 'imports/'.fake()->uuid().'.csv',
            'file_hash' => hash('sha256', fake()->uuid()),
            'file_size' => 1024,
            'status' => ImportStatus::Pending,
        ];
    }
}
