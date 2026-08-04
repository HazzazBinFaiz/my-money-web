<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
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
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'picture_id' => null,
            'account_id' => null,
        ];
    }
}
