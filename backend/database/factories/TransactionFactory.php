<?php

namespace Database\Factories;

use App\Domain\Finance\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'occurred_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'description' => fake()->sentence(3),
            'amount_cents' => fake()->numberBetween(100, 1_000_000),
            'type' => fake()->randomElement(TransactionType::cases()),
        ];
    }

    public function income(int $cents): static
    {
        return $this->state(['type' => TransactionType::Income, 'amount_cents' => $cents]);
    }

    public function expense(int $cents): static
    {
        return $this->state(['type' => TransactionType::Expense, 'amount_cents' => $cents]);
    }
}
