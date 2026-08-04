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
    $account = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);

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
        ->and($transaction->to_account_balance)->toBe(12550)
        ->and($transaction->from_account_balance)->toBe(0)
        ->and($transaction->to_account_id)->toBe($account->id)
        ->and($transaction->from_account_id)->toBeNull()
        ->and($transaction->created_at->format('Y-m-d H:i'))->toBe('2026-07-04 13:45');
});

test('an expense debits the account including the charge', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);

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
        ->and($transaction->from_account_balance)->toBe(7850)
        ->and($transaction->from_account_id)->toBe($account->id);
});

test('a transfer moves money between two accounts and takes no category', function () {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);
    $to = Account::factory()->for($user)->contact()->create(['initial_amount' => 500, 'amount' => 500]);

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
        ->and($transaction->from_account_balance)->toBe(3000)
        ->and($transaction->to_account_balance)->toBe(7500);
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
    $account = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);

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

test('deleting a transaction replays the balances of the ones after it', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);
    $category = expense($user);

    foreach ([['10', '09:00'], ['20', '10:00'], ['30', '11:00']] as [$amount, $time]) {
        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'date' => '2026-07-04',
            'time' => $time,
        ]);
    }

    $middle = Transaction::orderBy('created_at')->skip(1)->first();

    $this->actingAs($user)->delete(route('transactions.destroy', $middle))->assertRedirect();

    $balances = Transaction::orderBy('created_at')->pluck('from_account_balance')->all();

    expect($balances)->toBe([9000, 6000])
        ->and($account->fresh()->amount)->toBe(6000);
});

test('editing a transaction replays both the old and the new account', function () {
    $user = User::factory()->create();
    $cash = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);
    $bank = Account::factory()->for($user)->create(['initial_amount' => 5000, 'amount' => 5000]);
    $category = expense($user);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id,
        'category_id' => $category->id,
        'amount' => '25',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    $transaction = Transaction::first();

    $this->actingAs($user)->put(route('transactions.update', $transaction), [
        'type' => TransactionType::Expense->value,
        'account_id' => $bank->id,
        'category_id' => $category->id,
        'amount' => '10',
        'date' => '2026-07-04',
        'time' => '10:00',
    ])->assertRedirect(route('transactions.index'));

    expect($cash->fresh()->amount)->toBe(10000)
        ->and($bank->fresh()->amount)->toBe(4000)
        ->and($transaction->fresh()->from_account_balance)->toBe(4000);
});

test('a transfer takes amount plus charge from the source and gives only the amount', function () {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['initial_amount' => 10000, 'amount' => 10000]);
    $to = Account::factory()->for($user)->create(['initial_amount' => 0, 'amount' => 0]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Transfer->value,
        'account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '50',
        'charge' => '2.50',
        'date' => '2026-07-04',
        'time' => '10:00',
    ])->assertRedirect();

    $transaction = Transaction::first();

    expect($from->fresh()->amount)->toBe(4750)
        ->and($to->fresh()->amount)->toBe(5000)
        ->and($transaction->from_account_balance)->toBe(4750)
        ->and($transaction->to_account_balance)->toBe(5000);
});

test('the edit page renders with the transaction prefilled', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = expense($user);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '25',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.edit', Transaction::first()))
        ->assertOk()
        ->assertSee('25.00');
});

test('the list can be filtered by account and by category', function () {
    $user = User::factory()->create();
    $cash = Account::factory()->for($user)->create(['name' => 'Cash Account', 'initial_amount' => 100000, 'amount' => 100000]);
    $bank = Account::factory()->for($user)->create(['name' => 'Bank Account', 'initial_amount' => 100000, 'amount' => 100000]);
    $food = expense($user);
    $rent = expense($user);

    $post = fn (Account $account, Category $category, string $note) => $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => '10',
            'note' => $note,
            'date' => '2026-07-04',
            'time' => '10:00',
        ]);

    $post($cash, $food, 'cash and food');
    $post($bank, $rent, 'bank and rent');

    // Account filter.
    $this->actingAs($user)->get(route('transactions.index', ['account' => $cash->id]))
        ->assertOk()
        ->assertSee('cash and food')
        ->assertDontSee('bank and rent');

    // Category filter.
    $this->actingAs($user)->get(route('transactions.index', ['category' => $rent->id]))
        ->assertOk()
        ->assertSee('bank and rent')
        ->assertDontSee('cash and food');

    // Both together, with nothing matching.
    $this->actingAs($user)->get(route('transactions.index', ['account' => $cash->id, 'category' => $rent->id]))
        ->assertOk()
        ->assertSee('No transactions match these filters');
});

test('an account filter matches both sides of a transfer', function () {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['name' => 'Source', 'initial_amount' => 100000, 'amount' => 100000]);
    $to = Account::factory()->for($user)->create(['name' => 'Target', 'initial_amount' => 0, 'amount' => 0]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Transfer->value,
        'account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '50',
        'note' => 'moved money',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    foreach ([$from, $to] as $account) {
        $this->actingAs($user)->get(route('transactions.index', ['account' => $account->id]))
            ->assertOk()
            ->assertSee('moved money');
    }
});

test('filters survive the view toggle and paging', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Filtered', 'initial_amount' => 100000, 'amount' => 100000]);
    $category = expense($user);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '10',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    $content = $this->actingAs($user)
        ->get(route('transactions.index', ['account' => $account->id, 'view' => 'table']))
        ->assertOk()
        ->getContent();

    // The list/table switch carries the filter across.
    expect($content)->toContain('view=cards&amp;account='.$account->id);

    // Asking for a later page keeps filtering rather than falling back to everything.
    $this->actingAs($user)
        ->get(route('transactions.index', ['account' => $account->id, 'page' => 2]))
        ->assertOk()
        ->assertSee('1 matching transaction');
});
