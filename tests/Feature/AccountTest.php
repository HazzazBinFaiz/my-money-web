<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;

test('accounts page lists only account type rows', function () {
    $user = User::factory()->create();

    $account = Account::factory()->for($user)->create(['name' => 'Wallet']);
    Account::factory()->for($user)->contact()->create(['name' => 'John Doe']);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertSee('Wallet')
        ->assertDontSee('John Doe');

    expect($account->type)->toBe(AccountType::Account);
});

test('an account can be created with the current balance seeded from the initial amount', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.store'), ['name' => 'Bank', 'initial_amount' => 500])
        ->assertRedirect(route('accounts.index'));

    $account = Account::first();

    expect($account->name)->toBe('Bank')
        ->and($account->user_id)->toBe($user->id)
        ->and($account->initial_amount)->toBe(500)
        ->and($account->amount)->toBe(500)
        ->and($account->status)->toBe(AccountStatus::Active);
});

test('changing the initial amount shifts the current balance by the same delta', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_amount' => 100, 'amount' => 250]);

    $this->actingAs($user)
        ->put(route('accounts.update', $account), [
            'name' => 'Renamed',
            'initial_amount' => 300,
            'status' => AccountStatus::Inactive->value,
        ])->assertRedirect();

    $account->refresh();

    expect($account->name)->toBe('Renamed')
        ->and($account->amount)->toBe(450)
        ->and($account->status)->toBe(AccountStatus::Inactive);
});

test('accounts are scoped to their owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)
        ->put(route('accounts.update', $account), ['name' => 'Hacked', 'initial_amount' => 0, 'status' => 0])
        ->assertNotFound();
});
