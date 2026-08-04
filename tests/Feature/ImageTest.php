<?php

use App\Enums\ImageType;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a cropped png can be uploaded', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('images.store'), [
        'image' => UploadedFile::fake()->image('icon.png', 69, 69),
        'type' => ImageType::Account->value,
    ])->assertCreated();

    $image = Image::first();

    expect($image->user_id)->toBe($user->id)
        ->and($image->type)->toBe(ImageType::Account)
        ->and($image->image_name)->toEndWith('.png')
        ->and($response->json('data.id'))->toBe($image->id);

    Storage::disk('local')->assertExists('images/'.$image->image_name);
});

test('non png uploads are rejected', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('images.store'), [
        'image' => UploadedFile::fake()->image('icon.jpg'),
        'type' => ImageType::Account->value,
    ])->assertJsonValidationErrors('image');
});

test('the picker lists own and shared images of the requested type only', function () {
    $user = User::factory()->create();
    $own = Image::factory()->for($user)->create();
    $shared = Image::factory()->shared()->create(['type' => ImageType::Account]);
    Image::factory()->for($user)->picture()->create();
    Image::factory()->create();

    $ids = $this->actingAs($user)
        ->getJson(route('images.index', ['type' => ImageType::Account->value]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$own->id, $shared->id]);
});

test('another users image cannot be fetched or deleted', function () {
    $user = User::factory()->create();
    $foreign = Image::factory()->create();

    $this->actingAs($user)->get(route('images.show', $foreign))->assertNotFound();
    $this->actingAs($user)->deleteJson(route('images.destroy', $foreign))->assertNotFound();
});

test('shared images cannot be deleted', function () {
    $user = User::factory()->create();
    $shared = Image::factory()->shared()->create();

    $this->actingAs($user)->deleteJson(route('images.destroy', $shared))->assertForbidden();
});
