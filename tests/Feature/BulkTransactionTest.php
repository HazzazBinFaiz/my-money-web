<?php

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

function activeCategory(User $user, CategoryType $type): Category
{
    return Category::factory()->for($user)->create(['type' => $type, 'status' => CategoryStatus::Active]);
}

function row(array $overrides = []): array
{
    return array_merge([
        'type' => TransactionType::Expense->value,
        'account_id' => null,
        'to_account_id' => '',
        'category_id' => null,
        'amount' => '10',
        'charge' => '0',
        'note' => '',
        'date' => '2026-07-04',
        'time' => '10:30',
    ], $overrides);
}

test('the bulk page renders', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create();

    $this->actingAs($user)->get(route('transactions.bulk'))->assertOk();
});

test('many rows are saved in one go', function () {
    $user = User::factory()->create();
    $cash = Account::factory()->for($user)->create(['amount' => 100000]);
    $bank = Account::factory()->for($user)->create(['amount' => 0]);
    $income = activeCategory($user, CategoryType::Income);
    $expense = activeCategory($user, CategoryType::Expense);

    $this->actingAs($user)->post(route('transactions.bulk.store'), [
        'rows' => [
            row(['type' => TransactionType::Income->value, 'account_id' => $cash->id, 'category_id' => $income->id, 'amount' => '25.50']),
            row(['type' => TransactionType::Expense->value, 'account_id' => $cash->id, 'category_id' => $expense->id, 'amount' => '10 * 2', 'charge' => '1.50']),
            row(['type' => TransactionType::Transfer->value, 'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '100']),
        ],
    ])->assertRedirect(route('transactions.index'));

    expect(Transaction::count())->toBe(3)
        ->and($cash->fresh()->amount)->toBe(100000 + 2550 - 2150 - 10000)
        ->and($bank->fresh()->amount)->toBe(10000);
});

test('blank rows are ignored', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = activeCategory($user, CategoryType::Expense);

    $this->actingAs($user)->post(route('transactions.bulk.store'), [
        'rows' => [
            row(['account_id' => $account->id, 'category_id' => $category->id]),
            row(['account_id' => '', 'category_id' => '', 'amount' => '', 'to_account_id' => '']),
        ],
    ])->assertSessionHasNoErrors();

    expect(Transaction::count())->toBe(1);
});

test('a row holding only the default zero charge is ignored', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = activeCategory($user, CategoryType::Expense);

    $this->actingAs($user)->post(route('transactions.bulk.store'), [
        'rows' => [
            row(['account_id' => $account->id, 'category_id' => $category->id]),
            row(['account_id' => '', 'category_id' => '', 'amount' => '', 'charge' => '0']),
        ],
    ])->assertSessionHasNoErrors();

    expect(Transaction::count())->toBe(1);
});

test('one bad row rolls the whole batch back', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['amount' => 50000]);
    $category = activeCategory($user, CategoryType::Expense);

    $this->actingAs($user)->post(route('transactions.bulk.store'), [
        'rows' => [
            row(['account_id' => $account->id, 'category_id' => $category->id]),
            row(['account_id' => $account->id, 'category_id' => $category->id, 'amount' => 'phpinfo()']),
        ],
    ])->assertSessionHasErrors('rows.1.amount');

    expect(Transaction::count())->toBe(0)
        ->and($account->fresh()->amount)->toBe(50000);
});

test('each row is checked for ownership, status and type', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $foreign = Account::factory()->create();
    $income = activeCategory($user, CategoryType::Income);

    $this->actingAs($user)->post(route('transactions.bulk.store'), [
        'rows' => [
            row(['account_id' => $foreign->id, 'category_id' => $income->id]),
            row(['account_id' => $account->id, 'category_id' => $income->id]),
            row(['type' => TransactionType::Transfer->value, 'account_id' => $account->id, 'to_account_id' => $account->id]),
        ],
    ])->assertSessionHasErrors([
        'rows.0.account_id',
        'rows.1.category_id',
        'rows.2.to_account_id',
    ]);

    expect(Transaction::count())->toBe(0);
});

test('an empty submission is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.bulk.store'), ['rows' => [row(['account_id' => '', 'category_id' => '', 'amount' => ''])]])
        ->assertSessionHasErrors('rows');
});
