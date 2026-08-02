<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
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
        ->post(route('accounts.store'), ['name' => 'Bank', 'initial_amount' => '500.25'])
        ->assertRedirect(route('accounts.index'));

    $account = Account::first();

    expect($account->name)->toBe('Bank')
        ->and($account->user_id)->toBe($user->id)
        ->and($account->initial_amount)->toBe(50025)
        ->and($account->amount)->toBe(50025)
        ->and($account->status)->toBe(AccountStatus::Active);
});

test('changing the opening balance replays every balance recorded after it', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);
    $category = Category::factory()->for($user)->create([
        'type' => CategoryType::Expense,
        'status' => CategoryStatus::Active,
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '25',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    expect($account->fresh()->amount)->toBe(7500);

    $this->actingAs($user)
        ->put(route('accounts.update', $account), [
            'name' => 'Renamed',
            'initial_amount' => 300,
            'status' => AccountStatus::Inactive->value,
        ])->assertRedirect();

    $account->refresh();

    expect($account->name)->toBe('Renamed')
        ->and($account->amount)->toBe(27500)
        ->and(Transaction::first()->from_account_balance)->toBe(27500)
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
