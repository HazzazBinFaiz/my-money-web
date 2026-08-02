<?php

namespace Database\Factories;

use App\Enums\TransactionType;
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
            'type' => TransactionType::Expense,
            'category_id' => null,
            'amount' => 1000,
            'charge' => 0,
            'from_account_id' => null,
            'to_account_id' => null,
            'from_account_balance' => 0,
            'to_account_balance' => 0,
            'note' => null,
        ];
    }
}
