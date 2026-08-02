<?php

namespace Database\Factories;

use App\Enums\CurrencyPosition;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true).' book',
            'icon_id' => null,
            'is_default' => false,
            'decimal_places' => 2,
            'currency' => null,
            'currency_position' => CurrencyPosition::Before,
        ];
    }
}
