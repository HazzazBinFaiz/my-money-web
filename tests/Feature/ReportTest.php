<?php

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\ReportSummary;
use App\Support\DateRange;

function ledger(): array
{
    $user = User::factory()->create();

    $cash = Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 100000, 'amount' => 100000]);
    $bank = Account::factory()->for($user)->create(['name' => 'City Bank', 'initial_amount' => 0, 'amount' => 0]);

    $salary = Category::factory()->for($user)->create([
        'name' => 'Salary', 'type' => CategoryType::Income, 'status' => CategoryStatus::Active,
    ]);
    $food = Category::factory()->for($user)->create([
        'name' => 'Groceries', 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
    ]);

    return compact('user', 'cash', 'bank', 'salary', 'food');
}

function post($test, User $user, array $attributes, string $date = '2026-08-10'): void
{
    $test->actingAs($user)->post(route('transactions.store'), $attributes + [
        'date' => $date,
        'time' => '10:00',
    ])->assertSessionHasNoErrors();
}

test('the analysis pages render with their chart and table', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '25',
    ]);

    $this->actingAs($user)->get(route('reports.accounts', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertOk()
        ->assertSee('Account Analysis')
        ->assertSee('Income and expense per account')
        ->assertSee('Cash');

    $this->actingAs($user)->get(route('reports.categories', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertOk()
        ->assertSee('Category Analysis')
        ->assertSee('Income and expense per category')
        ->assertSee('Groceries');
});

test('per account totals split income from expense and count transfer charges as expense', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100', 'charge' => '2',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40', 'charge' => '1',
    ]);

    // Moving money is not spending, but the charge on it is.
    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '30', 'charge' => '3',
    ]);

    $this->actingAs($user);

    $rows = app(ReportSummary::class)
        ->perAccount(DateRange::fromRequest(null, '2026-08-01', '2026-08-31'))
        ->keyBy(fn (array $row) => $row['account']->name);

    expect($rows['Cash']['income'])->toBe(9800)
        ->and($rows['Cash']['expense'])->toBe(4100 + 300)
        // The receiving side of a transfer shows nothing either way.
        ->and($rows->has('City Bank'))->toBeFalse();
});

test('per category totals follow the category type', function () {
    ['user' => $user, 'cash' => $cash, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '500', 'charge' => '5',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '60', 'charge' => '2',
    ]);

    $this->actingAs($user);

    $rows = app(ReportSummary::class)
        ->perCategory(DateRange::fromRequest(null, '2026-08-01', '2026-08-31'))
        ->keyBy(fn (array $row) => $row['category']->name);

    expect($rows['Salary']['income'])->toBe(49500)
        ->and($rows['Salary']['expense'])->toBe(0)
        ->and($rows['Groceries']['expense'])->toBe(6200)
        ->and($rows['Groceries']['income'])->toBe(0);
});

test('the account breakdown reconciles opening balance to closing balance', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = ledger();

    // Before the range: moves the opening balance, stays out of the totals.
    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '100',
    ], '2026-07-15');

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '200', 'charge' => '5',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '50', 'charge' => '1',
    ]);

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '30', 'charge' => '2',
    ]);

    $this->actingAs($user);

    $detail = app(ReportSummary::class)->accountDetail(
        $cash->fresh(),
        DateRange::fromRequest(null, '2026-08-01', '2026-08-31')
    );

    expect($detail['opening'])->toBe(90000)
        ->and($detail['income'])->toBe(19500)
        ->and($detail['expense'])->toBe(5100)
        ->and($detail['transfer_out'])->toBe(3000)
        ->and($detail['transfer_charge'])->toBe(200)
        ->and($detail['transfer_in'])->toBe(0)
        // Opening plus everything that moved lands on the live balance.
        ->and($detail['closing'])->toBe(90000 + 19500 - 5100 - 3000 - 200)
        ->and($detail['closing'])->toBe($cash->fresh()->amount)
        ->and($detail['transactions'])->toHaveCount(3);
});

test('the receiving side of a transfer sees it as money in', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank] = ledger();

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '30', 'charge' => '2',
    ]);

    $this->actingAs($user);

    $detail = app(ReportSummary::class)->accountDetail(
        $bank->fresh(),
        DateRange::fromRequest(null, '2026-08-01', '2026-08-31')
    );

    expect($detail['transfer_in'])->toBe(3000)
        ->and($detail['transfer_out'])->toBe(0)
        ->and($detail['transfer_charge'])->toBe(0)
        ->and($detail['closing'])->toBe(3000);
});

test('the detail fragments render for the modal', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '25', 'note' => 'weekly shop',
    ]);

    $query = ['from' => '2026-08-01', 'to' => '2026-08-31'];

    $this->actingAs($user)->get(route('reports.accounts.detail', $cash).'?'.http_build_query($query))
        ->assertOk()
        ->assertSee('Starting balance')
        ->assertSee('Total expense')
        ->assertSee('Total income')
        ->assertSee('Transfer out')
        ->assertSee('Transfer charge')
        ->assertSee('End balance')
        ->assertSee('weekly shop');

    $this->actingAs($user)->get(route('reports.categories.detail', $food).'?'.http_build_query($query))
        ->assertOk()
        ->assertSee('Total expense')
        ->assertSee('Entries')
        ->assertSee('By account')
        ->assertSee('weekly shop');
});

test('reports stay inside the active book', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();
    $other = Book::factory()->for($user)->create();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '25',
    ]);

    $this->actingAs($user)->post(route('books.switch', $other));

    $this->actingAs($user)->get(route('reports.accounts', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertOk()
        ->assertDontSee('Cash')
        ->assertSee('No account activity in this range.');

    // Another book's account cannot be opened from this one either.
    $this->actingAs($user)->get(route('reports.accounts.detail', $cash))->assertNotFound();
});

test('the reports menu lists every report, with the unbuilt ones marked', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Reports')
        ->assertSee('Account Analysis')
        ->assertSee('Category Analysis')
        ->assertSee('Expense Overview')
        ->assertSee('Income Flow')
        ->assertSee('Soon');
});
