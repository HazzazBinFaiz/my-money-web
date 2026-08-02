<?php

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\ImageType;
use App\Models\Category;
use App\Models\Image;
use App\Models\User;

test('a category can be created with an icon', function () {
    $user = User::factory()->create();
    $icon = Image::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => CategoryType::Expense->value,
            'name' => 'Groceries',
            'icon_id' => $icon->id,
        ])->assertRedirect(route('categories.index'));

    $category = Category::first();

    expect($category->type)->toBe(CategoryType::Expense)
        ->and($category->icon_id)->toBe($icon->id)
        ->and($category->user_id)->toBe($user->id);
});

test('a category cannot use another users image', function () {
    $user = User::factory()->create();
    $foreignIcon = Image::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => CategoryType::Income->value,
            'name' => 'Salary',
            'icon_id' => $foreignIcon->id,
        ])->assertSessionHasErrors('icon_id');
});

test('a category cannot use a picture typed image', function () {
    $user = User::factory()->create();
    $picture = Image::factory()->for($user)->picture()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => CategoryType::Income->value,
            'name' => 'Salary',
            'icon_id' => $picture->id,
        ])->assertSessionHasErrors('icon_id');
});

test('shared images may be used by anyone', function () {
    $user = User::factory()->create();
    $shared = Image::factory()->shared()->create(['type' => ImageType::Icon]);

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => CategoryType::Income->value,
            'name' => 'Salary',
            'icon_id' => $shared->id,
        ])->assertSessionHasNoErrors();
});

test('a category starts active', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('categories.store'), [
        'type' => CategoryType::Expense->value,
        'name' => 'Rent',
    ]);

    expect(Category::first()->status)->toBe(CategoryStatus::Active);
});

test('the status can be toggled but the type stays fixed on update', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'type' => CategoryType::Expense,
        'status' => CategoryStatus::Active,
    ]);

    $this->actingAs($user)->put(route('categories.update', $category), [
        'name' => 'Rent',
        'status' => CategoryStatus::Inactive->value,
        'type' => CategoryType::Income->value,
    ])->assertRedirect();

    $category->refresh();

    expect($category->status)->toBe(CategoryStatus::Inactive)
        ->and($category->type)->toBe(CategoryType::Expense)
        ->and($category->name)->toBe('Rent');
});

test('status is required on update', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('categories.update', $category), ['name' => 'Rent'])
        ->assertSessionHasErrors('status');
});
