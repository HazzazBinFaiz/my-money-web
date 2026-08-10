<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

test('an unknown address gets the styled 404 page', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('Error 404', false)
        ->assertSee('Page not found')
        ->assertSee(config('app.name'))
        ->assertSee(config('site.contact_mail_address'));
});

test('a missing record gets the same page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('transactions.edit', 99999))
        ->assertNotFound()
        ->assertSee('Page not found');
});

test('the error pages cover the codes people actually hit', function (int $status, string $expected) {
    Route::get('/__error-test', fn () => abort($status))->middleware('web');

    $this->get('/__error-test')
        ->assertStatus($status)
        ->assertSee('Error '.$status, false)
        ->assertSee($expected);
})->with([
    [403, 'Not allowed'],
    [419, 'Your session expired'],
    [429, 'Too many requests'],
    [503, 'Down for maintenance'],
]);

test('a server error renders the 500 page rather than a stack trace', function () {
    // Debug is on while testing, which would otherwise show the trace page.
    config(['app.debug' => false]);

    Route::get('/__boom', fn () => throw new RuntimeException('kaboom'))->middleware('web');

    $this->get('/__boom')
        ->assertStatus(500)
        ->assertSee('Error 500', false)
        ->assertSee('Something broke on our side')
        ->assertDontSee('kaboom');
});

test('a status without its own page falls back to the catch alls', function () {
    config(['app.debug' => false]);

    Route::get('/__teapot', fn () => abort(418))->middleware('web');
    Route::get('/__gateway', fn () => abort(502))->middleware('web');

    $this->get('/__teapot')
        ->assertStatus(418)
        ->assertSee('Error 418', false)
        ->assertSee('That request could not be handled');

    $this->get('/__gateway')
        ->assertStatus(502)
        ->assertSee('Error 502', false)
        ->assertSee('Something broke on our side');
});

test('an expired token sends the visitor to the 419 page', function () {
    Route::post('/__stale', fn () => throw new TokenMismatchException)->middleware('web');

    $this->post('/__stale')
        ->assertStatus(419)
        ->assertSee('Your session expired');
});

test('a forbidden action renders the 403 page', function () {
    Route::get('/__forbidden', fn () => throw new AuthorizationException)->middleware('web');

    $this->get('/__forbidden')
        ->assertStatus(403)
        ->assertSee('Not allowed');
});
