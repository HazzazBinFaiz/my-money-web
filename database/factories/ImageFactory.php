<?php

namespace Database\Factories;

use App\Enums\ImageType;
use App\Models\Image;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ImageType::Icon,
            'image_name' => Str::uuid()->toString().'.png',
        ];
    }

    public function picture(): static
    {
        return $this->state(fn () => ['type' => ImageType::Picture]);
    }

    /** Shared image, owned by nobody. */
    public function shared(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }
}
