<?php

use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

function income(User $user): Category
{
    return Category::factory()->for($user)->create(['type' => CategoryType::Income, 'status' => CategoryStatus::Active]);
}

function expense(User $user): Category
{
    return Category::factory()->for($user)->create(['type' => CategoryType::Expense, 'status' => CategoryStatus::Active]);
}

test('an income credits the account and stores the closing balance', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['amount' => 10000]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Income->value,
        'account_id' => $account->id,
        'category_id' => income($user)->id,
        'amount' => '25.50',
        'date' => '2026-07-04',
        'time' => '13:45',
    ])->assertRedirect(route('transactions.index'));

    $transaction = Transaction::first();

    expect($account->fresh()->amount)->toBe(12550)
        ->and($transaction->amount)->toBe(2550)
        ->and($transaction->balance)->toBe(12550)
        ->and($transaction->to_account_id)->toBe($account->id)
        ->and($transaction->from_account_id)->toBeNull()
        ->and($transaction->created_at->format('Y-m-d H:i'))->toBe('2026-07-04 13:45');
});

test('an expense debits the account including the charge', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['amount' => 10000]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => expense($user)->id,
        'amount' => '10 * 2',
        'charge' => '1.50',
        'date' => '2026-07-04',
        'time' => '09:00',
    ])->assertRedirect();

    $transaction = Transaction::first();

    expect($transaction->amount)->toBe(2000)
        ->and($transaction->charge)->toBe(150)
        ->and($account->fresh()->amount)->toBe(7850)
        ->and($transaction->balance)->toBe(7850)
        ->and($transaction->from_account_id)->toBe($account->id);
});

test('a transfer moves money between two accounts and takes no category', function () {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['amount' => 10000]);
    $to = Account::factory()->for($user)->contact()->create(['amount' => 500]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Transfer->value,
        'account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '(4 * 4)+54',
        'date' => '2026-07-04',
        'time' => '10:30',
    ])->assertRedirect();

    $transaction = Transaction::first();

    expect($transaction->amount)->toBe(7000)
        ->and($transaction->category_id)->toBeNull()
        ->and($from->fresh()->amount)->toBe(3000)
        ->and($to->fresh()->amount)->toBe(7500)
        ->and($transaction->balance)->toBe(3000);
});

test('a transfer needs two different accounts', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Transfer->value,
        'account_id' => $account->id,
        'to_account_id' => $account->id,
        'amount' => '10',
        'date' => '2026-07-04',
        'time' => '10:30',
    ])->assertSessionHasErrors('to_account_id');
});

test('the category must match the transaction type and be active', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Income->value,
        'account_id' => $account->id,
        'category_id' => expense($user)->id,
        'amount' => '10',
        'date' => '2026-07-04',
        'time' => '10:30',
    ])->assertSessionHasErrors('category_id');

    $inactive = Category::factory()->for($user)->create([
        'type' => CategoryType::Income,
        'status' => CategoryStatus::Inactive,
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Income->value,
        'account_id' => $account->id,
        'category_id' => $inactive->id,
        'amount' => '10',
        'date' => '2026-07-04',
        'time' => '10:30',
    ])->assertSessionHasErrors('category_id');
});

test('inactive and foreign accounts are rejected', function () {
    $user = User::factory()->create();
    $inactive = Account::factory()->for($user)->create(['status' => AccountStatus::Inactive]);
    $foreign = Account::factory()->create();

    foreach ([$inactive->id, $foreign->id] as $accountId) {
        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'account_id' => $accountId,
            'category_id' => expense($user)->id,
            'amount' => '10',
            'date' => '2026-07-04',
            'time' => '10:30',
        ])->assertSessionHasErrors('account_id');
    }
});

test('a bogus amount expression is rejected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => expense($user)->id,
        'amount' => 'phpinfo()',
        'date' => '2026-07-04',
        'time' => '10:30',
    ])->assertSessionHasErrors('amount');

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => expense($user)->id,
        'amount' => '0',
        'date' => '2026-07-04',
        'time' => '10:30',
    ])->assertSessionHasErrors('amount');
});

test('deleting a transaction restores the balances', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['amount' => 10000]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => expense($user)->id,
        'amount' => '20',
        'charge' => '1',
        'date' => '2026-07-04',
        'time' => '10:30',
    ]);

    $this->actingAs($user)->delete(route('transactions.destroy', Transaction::first()))->assertRedirect();

    expect(Transaction::count())->toBe(0)
        ->and($account->fresh()->amount)->toBe(10000);
});

test('both list views render and transactions are scoped to their owner', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => expense($user)->id,
        'amount' => '20',
        'date' => '2026-07-04',
        'time' => '10:30',
    ]);

    $transaction = Transaction::first();

    $this->actingAs($user)->get(route('transactions.index'))->assertOk();
    $this->actingAs($user)->get(route('transactions.index', ['view' => 'table']))->assertOk();
    $this->actingAs($user)->get(route('transactions.create'))->assertOk();

    $this->actingAs(User::factory()->create())
        ->delete(route('transactions.destroy', $transaction))
        ->assertNotFound();
});
