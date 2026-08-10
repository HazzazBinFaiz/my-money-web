<?php

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\DashboardSummary;
use App\Support\DateRange;

function record($test, User $user, array $attributes): void
{
    $test->actingAs($user)->post(route('transactions.store'), $attributes + [
        'date' => '2026-08-02',
        'time' => '10:00',
    ]);
}

test('the dashboard renders with its tiles, charts and lists', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 100000, 'amount' => 100000]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Balance')
        ->assertSee('Money in')
        ->assertSee('Money out')
        ->assertSee('Money in and out')
        ->assertSee('Where it went')
        ->assertSee('Latest entries')
        ->assertSee('Cash');
});

test('income and expense totals count charges on the right side', function () {
    $user = User::factory()->create();
    $cash = Account::factory()->for($user)->create(['initial_amount' => 500000, 'amount' => 500000]);
    $bank = Account::factory()->for($user)->create(['initial_amount' => 0, 'amount' => 0]);

    $income = Category::factory()->for($user)->create(['type' => CategoryType::Income, 'status' => CategoryStatus::Active]);
    $expense = Category::factory()->for($user)->create(['type' => CategoryType::Expense, 'status' => CategoryStatus::Active]);

    // 100.00 in, 2.00 charge => 98.00 counted as income.
    record($this, $user, [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $income->id,
        'amount' => '100', 'charge' => '2',
    ]);

    // 50.00 out, 1.00 charge => 51.00 counted as expense.
    record($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $expense->id,
        'amount' => '50', 'charge' => '1',
    ]);

    // A transfer is not income or expense, but its charge still left the book.
    record($this, $user, [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id,
        'amount' => '200', 'charge' => '3',
    ]);

    $this->actingAs($user);

    $totals = app(DashboardSummary::class)->totals(
        DateRange::fromRequest(null, '2026-08-01', '2026-08-31')
    );

    expect($totals['income'])->toBe(9800)
        ->and($totals['expense'])->toBe(5100 + 300)
        ->and($totals['net'])->toBe(9800 - 5400)
        ->and($totals['fees'])->toBe(600)
        ->and($totals['count'])->toBe(3);
});

test('the range presets pick the right slice', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_amount' => 500000, 'amount' => 500000]);
    $expense = Category::factory()->for($user)->create(['type' => CategoryType::Expense, 'status' => CategoryStatus::Active]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id, 'category_id' => $expense->id,
        'amount' => '25',
        'date' => now()->format('Y-m-d'), 'time' => '10:00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id, 'category_id' => $expense->id,
        'amount' => '80',
        'date' => now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'), 'time' => '10:00',
    ]);

    $this->actingAs($user);

    $summary = app(DashboardSummary::class);

    expect($summary->totals(DateRange::fromRequest('this_month', null, null))['expense'])->toBe(2500)
        ->and($summary->totals(DateRange::fromRequest('last_month', null, null))['expense'])->toBe(8000)
        ->and($summary->totals(DateRange::fromRequest('all', null, null))['expense'])->toBe(10500);
});

test('a custom range wins over the preset and reversed dates are tolerated', function () {
    $range = DateRange::fromRequest('this_month', '2026-08-31', '2026-08-01');

    expect($range->preset)->toBe('custom')
        ->and($range->start->format('Y-m-d'))->toBe('2026-08-01')
        ->and($range->end->format('Y-m-d'))->toBe('2026-08-31')
        ->and($range->days())->toBe(31)
        ->and($range->grouping())->toBe('day');
});

test('long ranges are bucketed by month, short ones by day', function () {
    expect(DateRange::fromRequest('this_year', null, null)->grouping())->toBe('month')
        ->and(DateRange::fromRequest('last_30', null, null)->grouping())->toBe('day')
        ->and(DateRange::fromRequest('all', null, null)->grouping())->toBe('month');
});

test('the previous period is the same length, ending where this one starts', function () {
    $range = DateRange::fromRequest(null, '2026-08-10', '2026-08-19');
    $previous = $range->previous();

    expect($range->days())->toBe(10)
        ->and($previous->start->format('Y-m-d'))->toBe('2026-07-31')
        ->and($previous->end->format('Y-m-d'))->toBe('2026-08-09');
});

test('the series fills quiet days so the chart keeps its shape', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_amount' => 500000, 'amount' => 500000]);
    $expense = Category::factory()->for($user)->create(['type' => CategoryType::Expense, 'status' => CategoryStatus::Active]);

    record($this, $user, [
        'type' => TransactionType::Expense->value,
        'account_id' => $account->id, 'category_id' => $expense->id,
        'amount' => '30',
    ]);

    $this->actingAs($user);

    $series = app(DashboardSummary::class)->series(
        DateRange::fromRequest(null, '2026-08-01', '2026-08-07')
    );

    expect($series)->toHaveCount(7)
        ->and($series[1]['expense'])->toBe(3000)
        ->and($series[0]['expense'])->toBe(0)
        ->and($series[0]['income'])->toBe(0);
});

test('top categories rank spending and fold the tail into other', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_amount' => 1000000, 'amount' => 1000000]);

    foreach ([['Rent', 500], ['Food', 300], ['Bus', 100], ['Tea', 50], ['Gym', 40], ['Books', 30], ['Odds', 20], ['Ends', 10]] as [$name, $amount]) {
        $category = Category::factory()->for($user)->create([
            'name' => $name, 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
        ]);

        record($this, $user, [
            'type' => TransactionType::Expense->value,
            'account_id' => $account->id, 'category_id' => $category->id,
            'amount' => (string) $amount,
        ]);
    }

    $this->actingAs($user);

    $rows = app(DashboardSummary::class)->topCategories(
        DateRange::fromRequest(null, '2026-08-01', '2026-08-31')
    );

    expect($rows)->toHaveCount(7)
        ->and($rows[0]['name'])->toBe('Rent')
        ->and($rows->last()['name'])->toBe('Other')
        ->and($rows->last()['total'])->toBe(3000)
        ->and(round($rows->sum('share'), 6))->toBe(1.0);
});

test('dashboard figures are scoped to the active book', function () {
    $user = User::factory()->create();
    $other = Book::factory()->for($user)->create();

    Account::factory()->for($user)->create(['name' => 'Personal Cash', 'initial_amount' => 100000, 'amount' => 100000]);
    Account::factory()->for($user)->create(['name' => 'Business Cash', 'book_id' => $other->id, 'initial_amount' => 900000, 'amount' => 900000]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Personal Cash')
        ->assertDontSee('Business Cash');

    $this->actingAs($user)->post(route('books.switch', $other));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Business Cash')
        ->assertDontSee('Personal Cash');
});

test('an empty book gets pointers rather than zeroes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No accounts yet.')
        ->assertSee('Nothing recorded yet.')
        ->assertSee('Nothing recorded in this range yet.');
});

test('contact balances split into what people owe you and what you owe them', function () {
    $user = User::factory()->create();

    Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 50000, 'amount' => 50000]);

    // In credit: that money is out with them and coming back.
    Account::factory()->for($user)->contact()->create(['name' => 'Rina', 'initial_amount' => 30000, 'amount' => 30000]);
    Account::factory()->for($user)->contact()->create(['name' => 'Tanvir', 'initial_amount' => 12000, 'amount' => 12000]);

    // In debit: you are holding their money.
    Account::factory()->for($user)->contact()->create(['name' => 'Boro vai', 'initial_amount' => -20000, 'amount' => -20000]);

    // Settled up, so neither list should carry them.
    Account::factory()->for($user)->contact()->create(['name' => 'Settled', 'initial_amount' => 0, 'amount' => 0]);

    $this->actingAs($user);

    $balances = app(DashboardSummary::class)->balances();

    expect($balances['balance'])->toBe(50000)
        ->and($balances['lent'])->toBe(42000)
        ->and($balances['owed'])->toBe(20000)
        // What you are actually worth once the lending settles.
        ->and($balances['worth'])->toBe(50000 + 42000 - 20000)
        ->and($balances['borrowers']->pluck('name')->all())->toBe(['Rina', 'Tanvir'])
        ->and($balances['lenders']->pluck('name')->all())->toBe(['Boro vai'])
        // Contact accounts never inflate the money you actually hold.
        ->and($balances['accounts']->pluck('name')->all())->toBe(['Cash']);
});

test('the dashboard shows both sides of lending', function () {
    $user = User::factory()->create();

    Account::factory()->for($user)->contact()->create(['name' => 'Rina', 'initial_amount' => 30000, 'amount' => 30000]);
    Account::factory()->for($user)->contact()->create(['name' => 'Boro vai', 'initial_amount' => -20000, 'amount' => -20000]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('They owe you')
        ->assertSee('You owe them')
        ->assertSee('Rina')
        ->assertSee('owes you')
        ->assertSee('Boro vai')
        ->assertSee('you owe');
});

test('a book with no lending says so', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 50000, 'amount' => 50000]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Nothing lent or borrowed.')
        ->assertSee('across your own accounts');
});
