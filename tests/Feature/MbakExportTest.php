<?php

use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\ImageType;
use App\Enums\TransactionType;
use App\Lib\MbakFile;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Image;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MbakExporter;
use App\Services\MbakImporter;

function exportedPayload($test, User $user): array
{
    $response = $test->actingAs($user)->get(route('books.export.mbak'))->assertOk();

    return MbakFile::read($response->getContent());
}

test('the export round trips back through the reader', function () {
    $user = User::factory()->create();
    $cash = Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 10000, 'amount' => 10000]);
    $category = Category::factory()->for($user)->create([
        'name' => 'Salary',
        'type' => CategoryType::Income,
        'status' => CategoryStatus::Active,
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Income->value,
        'account_id' => $cash->id,
        'category_id' => $category->id,
        'amount' => '250.50',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    $payload = exportedPayload($this, $user);

    expect($payload['accounts'][0]['name'])->toBe('Cash')
        ->and($payload['accounts'][0]['initial'])->toEqual(100)
        ->and($payload['categories'][0])->toMatchArray(['name' => 'Salary', 'type' => 1])
        ->and($payload['records'][0]['type'])->toBe(1)
        ->and($payload['records'][0]['amount'])->toEqual(250.5)
        ->and($payload['records'][0]['account']['name'])->toBe('Cash')
        ->and($payload['budgets'])->toBe([]);
});

test('inactive accounts export with a leading dot', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Old wallet', 'status' => AccountStatus::Inactive]);
    Account::factory()->for($user)->create(['name' => 'Cash', 'status' => AccountStatus::Active]);

    $names = collect(exportedPayload($this, $user)['accounts'])->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['.Old wallet', 'Cash']);
});

test('a dotted account name imports back as an inactive account', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Old wallet', 'status' => AccountStatus::Inactive]);

    $exported = exportedPayload($this, $user);

    // Wipe the book, then feed the export back in.
    Transaction::query()->delete();
    Account::query()->delete();

    app(MbakImporter::class)->import($exported);

    $account = Account::firstWhere('name', 'Old wallet');

    expect($account)->not->toBeNull()
        ->and($account->status)->toBe(AccountStatus::Inactive)
        ->and(Account::where('name', '.Old wallet')->count())->toBe(0);
});

test('a charge is exported as a separate Transfer Charge expense', function () {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 100000, 'amount' => 100000]);
    $to = Account::factory()->for($user)->create(['name' => 'Bank', 'initial_amount' => 0, 'amount' => 0]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Transfer->value,
        'account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '500',
        'charge' => '12.50',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    $payload = exportedPayload($this, $user);

    $transfer = collect($payload['records'])->firstWhere('type', 3);
    $charge = collect($payload['records'])->firstWhere('type', 2);

    expect($payload['records'])->toHaveCount(2)
        ->and($transfer['amount'])->toEqual(500)
        ->and($transfer['transferFrom']['name'])->toBe('Cash')
        ->and($charge['amount'])->toEqual(12.5)
        ->and($charge['account']['name'])->toBe('Cash')
        ->and($charge['category']['name'])->toBe(MbakExporter::CHARGE_CATEGORY)
        ->and($charge['category']['type'])->toBe(2)
        // The charge record keeps its own id.
        ->and($charge['id'])->toBe($transfer['id'] + 1)
        ->and(collect($payload['categories'])->pluck('name'))->toContain(MbakExporter::CHARGE_CATEGORY);
});

test('exporting a charge and importing it back keeps the money in the same place', function () {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 100000, 'amount' => 100000]);
    $to = Account::factory()->for($user)->create(['name' => 'Bank', 'initial_amount' => 0, 'amount' => 0]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Transfer->value,
        'account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '500',
        'charge' => '12.50',
        'date' => '2026-07-04',
        'time' => '10:00',
    ]);

    $balances = Account::orderBy('name')->pluck('amount', 'name')->all();
    $exported = exportedPayload($this, $user);

    Transaction::query()->delete();
    Account::query()->delete();

    app(MbakImporter::class)->import($exported);

    expect(Account::orderBy('name')->pluck('amount', 'name')->all())->toBe($balances);
});

test('icons export using the image export id, falling back to the default', function () {
    $user = User::factory()->create();

    $mapped = Image::factory()->shared()->create(['export_icon_id' => '2131230902']);
    $unmapped = Image::factory()->for($user)->create();

    Account::factory()->for($user)->create(['name' => 'Cash', 'icon_id' => $mapped->id]);
    Account::factory()->for($user)->create(['name' => 'Bank', 'icon_id' => $unmapped->id]);
    Account::factory()->for($user)->create(['name' => 'Wallet', 'icon_id' => null]);

    Category::factory()->for($user)->create(['name' => 'Salary', 'icon_id' => $mapped->id]);

    $payload = exportedPayload($this, $user);
    $icons = collect($payload['accounts'])->pluck('icon', 'name');

    expect($icons['Cash'])->toBe(2131230902)
        ->and($icons['Bank'])->toBe(1)
        ->and($icons['Wallet'])->toBe(1)
        ->and($payload['categories'][0]['icon'])->toBe(2131230902);
});

test('icons survive an export and import round trip', function () {
    $user = User::factory()->create();

    $accountIcon = Image::factory()->shared()->create([
        'type' => ImageType::Account,
        'image_name' => 'accounts/account_cash.png',
        'export_icon_id' => '301',
    ]);
    $categoryIcon = Image::factory()->shared()->create([
        'type' => ImageType::Category,
        'image_name' => 'categories/category_salary.png',
        'export_icon_id' => '107',
    ]);

    Account::factory()->for($user)->create(['name' => 'Cash', 'icon_id' => $accountIcon->id]);
    Category::factory()->for($user)->create([
        'name' => 'Salary',
        'type' => CategoryType::Income,
        'status' => CategoryStatus::Active,
        'icon_id' => $categoryIcon->id,
    ]);

    $exported = exportedPayload($this, $user);

    expect(collect($exported['accounts'])->firstWhere('name', 'Cash')['icon'])->toBe(301)
        ->and(collect($exported['categories'])->firstWhere('name', 'Salary')['icon'])->toBe(107);

    Category::query()->delete();
    Account::query()->delete();

    app(MbakImporter::class)->import($exported);

    expect(Account::firstWhere('name', 'Cash')->icon_id)->toBe($accountIcon->id)
        ->and(Category::firstWhere('name', 'Salary')->icon_id)->toBe($categoryIcon->id);
});

test('only the active book is exported', function () {
    $user = User::factory()->create();
    $second = Book::factory()->for($user)->create();

    Account::factory()->for($user)->create(['name' => 'Personal Cash']);
    Account::factory()->for($user)->create(['name' => 'Business Cash', 'book_id' => $second->id]);

    $this->actingAs($user)->post(route('books.switch', $second));

    $names = collect(exportedPayload($this, $user)['accounts'])->pluck('name')->all();

    expect($names)->toBe(['Business Cash']);
});

test('the download is served as a file attachment', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('books.export.mbak'))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.mbak');
});
