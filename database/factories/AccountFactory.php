<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        $initial = fake()->numberBetween(0, 10000);

        return [
            'user_id' => User::factory(),
            'type' => AccountType::Account,
            'status' => AccountStatus::Active,
            'name' => fake()->words(2, true),
            'initial_amount' => $initial,
            'amount' => $initial,
            'icon_id' => null,
        ];
    }

    public function contact(): static
    {
        return $this->state(fn () => ['type' => AccountType::Contact]);
    }
}
