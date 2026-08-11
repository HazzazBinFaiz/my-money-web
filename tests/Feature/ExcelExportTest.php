<?php

use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\ExcelExporter;
use App\Support\DateRange;
use OpenSpout\Reader\XLSX\Reader;

/**
 * @return array<string, array<int, array<int, mixed>>> sheet name => rows
 */
function workbook(User $user, ?DateRange $range = null): array
{
    $path = tempnam(sys_get_temp_dir(), 'book').'.xlsx';

    app(ExcelExporter::class)->write(
        $user->defaultBook(),
        $range ?? DateRange::fromRequest('all', null, null),
        $path,
    );

    $reader = new Reader;
    $reader->open($path);

    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }

        $sheets[$sheet->getName()] = $rows;
    }

    $reader->close();
    unlink($path);

    return $sheets;
}

function seedBook(): array
{
    $user = User::factory()->create();

    $cash = Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 100000, 'amount' => 100000]);
    $bank = Account::factory()->for($user)->create(['name' => 'City Bank', 'initial_amount' => 0, 'amount' => 0]);
    Account::factory()->for($user)->create(['name' => 'Old wallet', 'status' => AccountStatus::Inactive]);

    $salary = Category::factory()->for($user)->create([
        'name' => 'Salary', 'type' => CategoryType::Income, 'status' => CategoryStatus::Active,
    ]);
    $food = Category::factory()->for($user)->create([
        'name' => 'Groceries', 'type' => CategoryType::Expense, 'status' => CategoryStatus::Active,
    ]);

    return compact('user', 'cash', 'bank', 'salary', 'food');
}

test('the workbook leads with transactions and carries a sheet each for the rest', function () {
    ['user' => $user] = seedBook();

    $sheets = workbook($user);

    expect(array_keys($sheets))->toBe(['Transactions', 'Accounts', 'Categories', 'Contacts'])
        ->and($sheets['Transactions'][0])->toBe([
            'Date', 'Time', 'Type', 'Category', 'From account', 'To account',
            'Amount', 'Charge', 'From balance', 'To balance', 'Note',
        ]);
});

test('transactions export with both sides, amounts as numbers in major units', function () {
    ['user' => $user, 'cash' => $cash, 'bank' => $bank, 'salary' => $salary, 'food' => $food] = seedBook();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id, 'category_id' => $salary->id,
        'amount' => '250.50', 'note' => 'August pay',
        'date' => '2026-08-01', 'time' => '09:00',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Transfer->value,
        'account_id' => $cash->id, 'to_account_id' => $bank->id,
        'amount' => '100', 'charge' => '1.50',
        'date' => '2026-08-02', 'time' => '10:00',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user);

    $rows = workbook($user)['Transactions'];

    [$date, $time, $type, $category, $from, $to, $amount, $charge] = $rows[1];

    expect($date)->toBe('2026-08-01')
        ->and($time)->toBe('09:00')
        ->and($type)->toBe('Income')
        ->and($category)->toBe('Salary')
        ->and($from)->toBe('')
        ->and($to)->toBe('Cash')
        // A number, not a string, so the column can be summed.
        ->and($amount)->toBe(250.5)
        ->and($charge)->toEqual(0);

    $transfer = $rows[2];

    expect($transfer[2])->toBe('Transfer')
        ->and($transfer[3])->toBe('')
        ->and($transfer[4])->toBe('Cash')
        ->and($transfer[5])->toBe('City Bank')
        ->and($transfer[7])->toBe(1.5);
});

test('the accounts sheet reports kind, status and both balances', function () {
    ['user' => $user] = seedBook();

    $rows = collect(workbook($user)['Accounts'])->skip(1)->keyBy(fn (array $row) => $row[0]);

    expect($rows['Cash'][1])->toBe('Account')
        ->and($rows['Cash'][2])->toBe('Active')
        ->and($rows['Cash'][3])->toEqual(1000)
        ->and($rows['Cash'][4])->toEqual(1000)
        ->and($rows['Old wallet'][2])->toBe('Inactive');
});

test('contacts export with their standing spelled out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('contacts.store'), [
        'name' => 'Rina', 'phone' => '0123', 'initial_amount' => '250',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('contacts.store'), [
        'name' => 'Boro vai', 'initial_amount' => '-150',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user);

    $rows = collect(workbook($user)['Contacts'])->skip(1)->keyBy(fn (array $row) => $row[0]);

    expect($rows['Rina'][1])->toBe('0123')
        ->and($rows['Rina'][3])->toEqual(250)
        ->and($rows['Rina'][4])->toBe('Owes you')
        ->and($rows['Boro vai'][3])->toEqual(-150)
        ->and($rows['Boro vai'][4])->toBe('You owe');
});

test('amounts keep two decimals even when the book displays none', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = seedBook();

    $user->defaultBook()->update(['decimal_places' => 0]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Expense->value,
        'account_id' => $cash->id, 'category_id' => $food->id,
        'amount' => '12.34',
        'date' => '2026-08-01', 'time' => '09:00',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user);

    // Rounding to the book's display setting here would quietly lose money.
    expect(workbook($user)['Transactions'][1][6])->toBe(12.34);
});

test('the range narrows the transactions sheet but not the other three', function () {
    ['user' => $user, 'cash' => $cash, 'food' => $food] = seedBook();

    foreach (['2026-07-15', '2026-08-10'] as $date) {
        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'account_id' => $cash->id, 'category_id' => $food->id,
            'amount' => '10',
            'date' => $date, 'time' => '09:00',
        ])->assertSessionHasNoErrors();
    }

    $this->actingAs($user);

    $sheets = workbook($user, DateRange::fromRequest(null, '2026-08-01', '2026-08-31'));

    expect($sheets['Transactions'])->toHaveCount(2)
        ->and($sheets['Accounts'])->toHaveCount(4)
        ->and($sheets['Categories'])->toHaveCount(3);
});

test('the download arrives as a spreadsheet', function () {
    ['user' => $user] = seedBook();

    $response = $this->actingAs($user)->get(route('books.export.excel'))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx')
        ->and($response->headers->get('content-type'))
        ->toContain('spreadsheetml');
});

test('the export covers only the active book', function () {
    ['user' => $user] = seedBook();
    $other = Book::factory()->for($user)->create();

    Account::factory()->for($user)->create(['name' => 'Business Cash', 'book_id' => $other->id]);

    $this->actingAs($user);

    $names = collect(workbook($user)['Accounts'])->skip(1)->pluck(0);

    expect($names)->toContain('Cash')
        ->and($names)->not->toContain('Business Cash');
});
