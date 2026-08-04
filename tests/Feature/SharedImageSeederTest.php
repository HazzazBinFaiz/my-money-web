<?php

use App\Enums\ImageType;
use App\Models\Image;
use App\Models\User;
use Database\Seeders\SharedImageSeeder;

test('it seeds shared icons that anyone can use but nobody owns', function () {
    $this->seed(SharedImageSeeder::class);

    $image = Image::withoutGlobalScopes()->first();

    expect(Image::withoutGlobalScopes()->count())->toBeGreaterThan(100)
        ->and(Image::withoutGlobalScopes()->whereNotNull('user_id')->count())->toBe(0)
        // Icons are typed by the folder they live in.
        ->and(Image::withoutGlobalScopes()->where('type', ImageType::Account)->count())->toBeGreaterThan(0)
        ->and(Image::withoutGlobalScopes()->where('type', ImageType::Category)->count())->toBeGreaterThan(0)
        // Nothing is copied: the row points at the file already in public/images.
        ->and(file_exists(public_path('images/'.$image->image_name)))->toBeTrue()
        ->and($image->url)->toBe(asset('images/'.$image->image_name));
});

test('every seeded row points at a file that exists', function () {
    $this->seed(SharedImageSeeder::class);

    $missing = Image::withoutGlobalScopes()
        ->pluck('image_name')
        ->reject(fn (string $name) => file_exists(public_path('images/'.$name)))
        ->all();

    expect($missing)->toBe([]);
});

test('shared icons are served straight from public', function () {
    $this->seed(SharedImageSeeder::class);

    $user = User::factory()->create();
    $image = Image::withoutGlobalScopes()->first();

    $this->actingAs($user)
        ->get(route('images.show', $image))
        ->assertRedirect(asset('images/'.$image->image_name));
});

test('seeded icons show up in the picker for every user', function () {
    $this->seed(SharedImageSeeder::class);

    $user = User::factory()->create();

    $count = count($this->actingAs($user)
        ->getJson(route('images.index', ['type' => ImageType::Account->value]))
        ->assertOk()
        ->json('data'));

    expect($count)->toBe(Image::withoutGlobalScopes()->where('type', ImageType::Account)->count())
        // Shared images are not editable.
        ->and(Image::withoutGlobalScopes()->first()->isEditableBy($user))->toBeFalse();
});

test('re-running the seeder updates instead of duplicating', function () {
    $this->seed(SharedImageSeeder::class);
    $first = Image::withoutGlobalScopes()->count();

    $this->seed(SharedImageSeeder::class);

    expect(Image::withoutGlobalScopes()->count())->toBe($first);
});
