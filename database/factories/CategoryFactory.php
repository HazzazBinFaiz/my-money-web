<?php

namespace Database\Factories;

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Models\Book;
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
            'book_id' => fn (array $attributes) => Book::withoutGlobalScopes()
                ->where('user_id', $attributes['user_id'])
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id') ?? Book::factory()->create(['user_id' => $attributes['user_id']])->id,
            'type' => fake()->randomElement(CategoryType::cases()),
            'status' => CategoryStatus::Active,
            'name' => fake()->word(),
            'icon_id' => null,
        ];
    }
}
