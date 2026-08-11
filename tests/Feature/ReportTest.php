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
use App\Support\ReportFilter;

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

test('the overview pages render their pie, total and share column', function () {
    ['user' => $user, 'cash' => $cash, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '75',
    ]);

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '300',
    ]);

    $query = ['from' => '2026-08-01', 'to' => '2026-08-31'];

    $this->actingAs($user)->get(route('reports.expenses', $query))
        ->assertOk()
        ->assertSee('Expense Overview')
        ->assertSee('Where the money went')
        ->assertSee('Groceries')
        ->assertSee('100.0%')
        ->assertDontSee('Salary');

    $this->actingAs($user)->get(route('reports.incomes', $query))
        ->assertOk()
        ->assertSee('Income Overview')
        ->assertSee('Where the income came from')
        ->assertSee('Salary')
        ->assertDontSee('Groceries');
});

test('overview shares are of the side total and add up to the whole', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    $rent = Category::factory()->for($user)->create([
        'name' => 'Rent', 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '25',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $rent->id, 'amount' => '74', 'charge' => '1',
    ]);

    $this->actingAs($user);

    $overview = app(ReportSummary::class)
        ->overview(CategoryType::Expense, DateRange::fromRequest(null, '2026-08-01', '2026-08-31'));

    // Biggest first, and charges are part of what the category cost.
    expect($overview['total'])->toBe(10000)
        ->and($overview['rows']->pluck('name')->all())->toBe(['Rent', 'Groceries'])
        ->and($overview['rows'][0]['total'])->toBe(7500)
        ->and($overview['rows'][0]['share'])->toBe(0.75)
        ->and($overview['rows']->sum('share'))->toBe(1.0);
});

test('transfer charges ride along as their own expense slice', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '90',
    ]);

    // No category of its own, but the money still left the book.
    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '500', 'charge' => '10',
    ]);

    $this->actingAs($user);

    $overview = app(ReportSummary::class)
        ->overview(CategoryType::Expense, DateRange::fromRequest(null, '2026-08-01', '2026-08-31'));

    expect($overview['total'])->toBe(10000)
        ->and($overview['rows']->firstWhere('key', 'charges')['total'])->toBe(1000)
        // Nothing to drill into: the charge belongs to no category.
        ->and($overview['rows']->firstWhere('key', 'charges')['category'])->toBeNull();

    // The transfer itself is a move, not spending.
    expect($overview['rows']->sum('total'))->toBe(10000);
});

test('income overview never counts transfer charges', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100', 'charge' => '10',
    ]);

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '20', 'charge' => '5',
    ]);

    $this->actingAs($user);

    $overview = app(ReportSummary::class)
        ->overview(CategoryType::Income, DateRange::fromRequest(null, '2026-08-01', '2026-08-31'));

    expect($overview['total'])->toBe(9000)
        ->and($overview['rows'])->toHaveCount(1);
});

test('the overview modal shows the period, the share and the transactions', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    $rent = Category::factory()->for($user)->create([
        'name' => 'Rent', 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '25', 'note' => 'weekly shop',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $rent->id, 'amount' => '75',
    ]);

    $query = ['from' => '2026-08-01', 'to' => '2026-08-31'];

    $this->actingAs($user)->get(route('reports.overview.detail', $food).'?'.http_build_query($query))
        ->assertOk()
        ->assertSee('1 Aug 2026 – 31 Aug 2026')
        ->assertSee('25.0%')
        ->assertSee('of spending in this period')
        ->assertSee('Total expense')
        ->assertSee('weekly shop');
});

test('a category from another book cannot be opened from the overview', function () {
    ['user' => $user, 'food' => $food] = ledger();
    $other = Book::factory()->for($user)->create();

    $this->actingAs($user)->post(route('books.switch', $other));

    $this->actingAs($user)->get(route('reports.overview.detail', $food))->assertNotFound();
});

test('the flow calendar renders whole months and greys what the range misses', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40',
    ], '2026-08-12');

    // A part month still draws end to end; the days outside it are just dead.
    $this->actingAs($user)->get(route('reports.expense-flow', ['from' => '2026-08-10', 'to' => '2026-08-20']))
        ->assertOk()
        ->assertSee('Expense Flow')
        ->assertSee('August 2026')
        ->assertSee('1 active day')
        // The 12th carries the money and can be opened; the 3rd is out of range.
        ->assertSee('data-day="2026-08-12"', false)
        ->assertDontSee('data-day="2026-08-03"', false);
});

test('a range spanning months draws one calendar each', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '10',
    ], '2026-07-05');

    $this->actingAs($user)->get(route('reports.expense-flow', ['from' => '2026-06-15', 'to' => '2026-08-05']))
        ->assertOk()
        ->assertSee('June 2026')
        ->assertSee('July 2026')
        ->assertSee('August 2026');
});

test('daily flow totals follow the same charge rules as the overview', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40', 'charge' => '2',
    ], '2026-08-10');

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '100', 'charge' => '3',
    ], '2026-08-10');

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '500', 'charge' => '5',
    ], '2026-08-11');

    $this->actingAs($user);

    $range = DateRange::fromRequest(null, '2026-08-01', '2026-08-31');
    $summary = app(ReportSummary::class);

    $expense = $summary->dailyFlow(CategoryType::Expense, $range);
    $income = $summary->dailyFlow(CategoryType::Income, $range);

    // The transfer itself never lands, only its charge.
    expect($expense['days'])->toBe(['2026-08-10' => 4500])
        ->and($expense['total'])->toBe($summary->overview(CategoryType::Expense, $range)['total'])
        ->and($income['days'])->toBe(['2026-08-11' => 49500])
        ->and($income['busiest'])->toBe('2026-08-11');
});

test('a day opens to its transactions and nothing from the other side', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40', 'note' => 'market run',
    ], '2026-08-10');

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '500', 'note' => 'august pay',
    ], '2026-08-10');

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '100', 'charge' => '3',
    ], '2026-08-10');

    $this->actingAs($user)->get(route('reports.flow.day', ['type' => 'expense', 'date' => '2026-08-10']))
        ->assertOk()
        ->assertSee('Monday, 10 August 2026')
        ->assertSee('market run')
        // The charge belongs here; the transfer's own amount does not.
        ->assertSee('Transfer charge')
        ->assertDontSee('august pay');

    $this->actingAs($user)->get(route('reports.flow.day', ['type' => 'income', 'date' => '2026-08-10']))
        ->assertOk()
        ->assertSee('august pay')
        ->assertDontSee('market run')
        ->assertDontSee('Transfer charge');
});

test('the flow day fragment rejects a bad side or date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('reports.flow.day', ['type' => 'profit', 'date' => '2026-08-10']))->assertNotFound();
    $this->actingAs($user)->get(route('reports.flow.day', ['type' => 'expense', 'date' => 'yesterday']))->assertNotFound();
});

test('the flow day stays inside the active book', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();
    $other = Book::factory()->for($user)->create();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40', 'note' => 'market run',
    ], '2026-08-10');

    $this->actingAs($user)->post(route('books.switch', $other));

    $this->actingAs($user)->get(route('reports.flow.day', ['type' => 'expense', 'date' => '2026-08-10']))
        ->assertOk()
        ->assertDontSee('market run')
        ->assertSee('Nothing recorded on this day.');
});

test('an open ended range falls back to a month the calendar can draw', function () {
    ['user' => $user] = ledger();

    $this->actingAs($user)->get(route('reports.expense-flow', ['range' => 'all']))
        ->assertOk()
        ->assertSee('This month')
        ->assertSee(now()->isoFormat('MMMM YYYY'));
});

test('the account filter matches the side the money moved on', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '30',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '70',
    ]);

    // Money arriving from a transfer is not income, however the filter is set.
    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $bank->id, 'to_account_id' => $cash->id, 'amount' => '200', 'charge' => '4',
    ]);

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '500',
    ]);

    $this->actingAs($user);

    $range = DateRange::fromRequest(null, '2026-08-01', '2026-08-31');
    $summary = app(ReportSummary::class);

    $spentFromCash = $summary->overview(CategoryType::Expense, $range, new ReportFilter($cash->id));
    $spentFromBank = $summary->overview(CategoryType::Expense, $range, new ReportFilter($bank->id));
    $intoBank = $summary->overview(CategoryType::Income, $range, new ReportFilter($bank->id));

    expect($spentFromCash['total'])->toBe(3000)
        // The bank paid its own expense and the transfer charge.
        ->and($spentFromBank['total'])->toBe(7000 + 400)
        ->and($intoBank['total'])->toBe(0);
});

test('the category filter narrows the flow calendar', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    $rent = Category::factory()->for($user)->create([
        'name' => 'Rent', 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '30',
    ], '2026-08-10');

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $rent->id, 'amount' => '70',
    ], '2026-08-11');

    $this->actingAs($user);

    $flow = app(ReportSummary::class)->dailyFlow(
        CategoryType::Expense,
        DateRange::fromRequest(null, '2026-08-01', '2026-08-31'),
        new ReportFilter(null, $rent->id),
    );

    expect($flow['days'])->toBe(['2026-08-11' => 7000]);
});

test('a filtered flow page keeps the filter on the days it opens', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '30',
    ], '2026-08-10');

    $this->actingAs($user)->get(route('reports.expense-flow', [
        'from' => '2026-08-01', 'to' => '2026-08-31', 'account' => $cash->id,
    ]))
        ->assertOk()
        ->assertSee('Paid from')
        ->assertSee('data-day="2026-08-10"', false)
        ->assertSee('account='.$cash->id, false);
});

test('the flow day fragment honours the filter it is opened with', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '30', 'note' => 'cash shop',
    ], '2026-08-10');

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '70', 'note' => 'card shop',
    ], '2026-08-10');

    $this->actingAs($user)->get(route('reports.flow.day', [
        'type' => 'expense', 'date' => '2026-08-10', 'account' => $cash->id,
    ]))
        ->assertOk()
        ->assertSee('cash shop')
        ->assertDontSee('card shop');
});

test('the overview modal shares are of the filtered total', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'food' => $food] = ledger();

    $rent = Category::factory()->for($user)->create([
        'name' => 'Rent', 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '25',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $rent->id, 'amount' => '75',
    ]);

    // Paid from elsewhere, so it must not dilute the share once Cash is picked.
    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $bank->id, 'category_id' => $rent->id, 'amount' => '900',
    ]);

    $query = ['from' => '2026-08-01', 'to' => '2026-08-31', 'account' => $cash->id];

    $this->actingAs($user)->get(route('reports.overview.detail', $food).'?'.http_build_query($query))
        ->assertOk()
        ->assertSee('25.0%');
});

test('the overview offers no category filter', function () {
    ['user' => $user] = ledger();

    $this->actingAs($user)->get(route('reports.expenses'))
        ->assertOk()
        ->assertSee('All accounts')
        ->assertDontSee('All categories');

    $this->actingAs($user)->get(route('reports.expense-flow'))
        ->assertOk()
        ->assertSee('All categories');
});

test('money flow joins income categories to accounts to expense categories', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40',
    ]);

    $this->actingAs($user);

    $flow = app(ReportSummary::class)->moneyFlow(DateRange::fromRequest(null, '2026-08-01', '2026-08-31'));

    $account = $flow['accounts']->firstWhere('id', 'account-'.$cash->id);

    expect($flow['income']->pluck('id')->all())->toBe(['category-'.$salary->id])
        ->and($flow['expense']->pluck('id')->all())->toBe(['category-'.$food->id])
        // Spent less than it took in, so the node stands as tall as the income.
        ->and($account['in'])->toBe(10000)
        ->and($account['out'])->toBe(4000)
        ->and($account['total'])->toBe(10000)
        ->and($flow['links']->pluck('value', 'source')->all())->toBe([
            'category-'.$salary->id => 10000,
            'account-'.$cash->id => 4000,
        ])
        ->and($flow['total_in'])->toBe(10000)
        ->and($flow['total_out'])->toBe(4000)
        // An account with no movement of its own stays off the diagram.
        ->and($flow['accounts']->pluck('id'))->not->toContain('account-'.$bank->id);
});

test('a transfer joins the two accounts and its charge lands as spending', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100',
    ]);

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '50', 'charge' => '2',
    ]);

    $this->actingAs($user);

    $flow = app(ReportSummary::class)->moneyFlow(DateRange::fromRequest(null, '2026-08-01', '2026-08-31'));

    $move = $flow['links']->firstWhere('side', 'transfer');

    // The 50 moved inside the book: it joins the accounts but never counts as
    // money in or out. Only the charge left.
    expect($flow['total_out'])->toBe(200)
        ->and($flow['total_in'])->toBe(10000)
        ->and($move['source'])->toBe('account-'.$cash->id)
        ->and($move['target'])->toBe('account-'.$bank->id)
        ->and($move['value'])->toBe(5000)
        ->and($flow['expense']->pluck('id')->all())->toBe(['charges'])
        ->and($flow['expense']->first()['name'])->toBe('Transfer charges')
        ->and($flow['links']->firstWhere('target', 'charges')['value'])->toBe(200)
        // The receiving account is now on the diagram, joined on its left.
        ->and($flow['accounts']->firstWhere('id', 'account-'.$bank->id)['in'])->toBe(5000);
});

test('an account filter keeps the transfers on either side of it', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100',
    ]);

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '50',
    ]);

    $this->actingAs($user);

    $range = DateRange::fromRequest(null, '2026-08-01', '2026-08-31');
    $summary = app(ReportSummary::class);

    // Whichever end is picked, the same ribbon belongs on the diagram.
    foreach ([$cash->id, $bank->id] as $id) {
        expect($summary->moneyFlow($range, new ReportFilter($id))['links']->where('side', 'transfer'))
            ->toHaveCount(1);
    }
});

test('a category filter leaves transfers out, having no category to match', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100',
    ]);

    post($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => '50',
    ]);

    $this->actingAs($user);

    $flow = app(ReportSummary::class)->moneyFlow(
        DateRange::fromRequest(null, '2026-08-01', '2026-08-31'),
        new ReportFilter(null, $salary->id),
    );

    expect($flow['links']->where('side', 'transfer'))->toBeEmpty();
});

test('an account node is as tall as its bigger side', function () {
    ['user' => $user, 'cash' => $cash, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '30',
    ]);

    // Spending out of an opening balance: more goes out than came in.
    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '80',
    ]);

    $this->actingAs($user);

    $flow = app(ReportSummary::class)->moneyFlow(DateRange::fromRequest(null, '2026-08-01', '2026-08-31'));

    expect($flow['accounts']->first()['total'])->toBe(8000);
});

test('money flow filters narrow the side they belong to', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = ledger();

    $rent = Category::factory()->for($user)->create([
        'name' => 'Rent', 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
    ]);

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $bank->id, 'category_id' => $rent->id, 'amount' => '70',
    ]);

    $this->actingAs($user);

    $range = DateRange::fromRequest(null, '2026-08-01', '2026-08-31');
    $summary = app(ReportSummary::class);

    // An expense category narrows the right side; the left keeps its picture,
    // or the accounts it fed would be left standing on nothing.
    $byCategory = $summary->moneyFlow($range, new ReportFilter(null, $rent->id));

    expect($byCategory['expense']->pluck('name')->all())->toBe(['Rent'])
        ->and($byCategory['income']->pluck('name')->all())->toBe(['Salary'])
        ->and($byCategory['total_out'])->toBe(7000);

    $byAccount = $summary->moneyFlow($range, new ReportFilter($cash->id));

    expect($byAccount['accounts']->pluck('id')->all())->toBe(['account-'.$cash->id])
        ->and($byAccount['expense']->pluck('name')->all())->toBe(['Groceries']);
});

test('the money flow page renders its diagram and the table behind it', function () {
    ['user' => $user, 'cash' => $cash, 'salary' => $salary, 'food' => $food] = ledger();

    post($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id, 'amount' => '100',
    ]);

    post($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id, 'amount' => '40',
    ]);

    $this->actingAs($user)->get(route('reports.money-flow', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertOk()
        ->assertSee('Money Flow')
        ->assertSee('Where the money came from, and where it went')
        ->assertSee('Salary')
        ->assertSee('Groceries')
        ->assertSee('Cash')
        // Both filters, and the numbers repeated without a hover.
        ->assertSee('All accounts')
        ->assertSee('All categories')
        // The diagram is laid out in the browser, so the page carries the graph
        // rather than the ribbons.
        ->assertSee('moneyFlow(', false)
        ->assertSee('account-'.$cash->id, false)
        ->assertSee('Kept');
});

test('money flow says so when nothing moved', function () {
    ['user' => $user] = ledger();

    $this->actingAs($user)->get(route('reports.money-flow'))
        ->assertOk()
        ->assertSee('Nothing moved in this range.');
});

test('the reports menu links every report', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Reports')
        ->assertSee('Account Analysis')
        ->assertSee('Category Analysis')
        ->assertSee('Expense Overview')
        ->assertSee('Money Flow')
        ->assertSee('Income Flow')
        ->assertSee(route('reports.expense-flow'), false)
        ->assertSee(route('reports.incomes'), false)
        ->assertDontSee('Soon');
});
