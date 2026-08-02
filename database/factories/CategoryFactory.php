<?php

namespace Database\Factories;

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(CategoryType::cases()),
            'status' => CategoryStatus::Active,
            'name' => fake()->word(),
            'icon_id' => null,
        ];
    }
}
