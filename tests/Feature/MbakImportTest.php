<?php

use App\Enums\AccountStatus;
use App\Enums\CategoryType;
use App\Enums\ImageType;
use App\Enums\TransactionType;
use App\Lib\CryptoKeyDeriver;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Image;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function mbakFile(array $payload): UploadedFile
{
    $keys = CryptoKeyDeriver::deriveKeyAndHmac();
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt(json_encode($payload), 'AES-128-CBC', $keys->aesKey, OPENSSL_RAW_DATA, $iv);
    $mac = hash_hmac('sha256', $iv.$ciphertext, $keys->hmacKey, true);

    $contents = implode(':', [base64_encode($iv), base64_encode($mac), base64_encode($ciphertext)]);

    $path = tempnam(sys_get_temp_dir(), 'mbak').'.mbak';
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'backup.mbak', null, null, true);
}

function samplePayload(): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/mbak-sample.json'), true);
}

test('the sample backup imports into the active book', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())])
        ->assertRedirect(route('books.index'))
        ->assertSessionHas('mbak_summary');

    // Two listed accounts plus three that only appear inside records.
    // ".Reverse debt" loses its dot and lands inactive.
    expect(Account::pluck('name')->sort()->values()->all())
        ->toBe(['Boro vai', 'Cash', 'DBBL', 'IFIC', 'Reverse debt'])
        ->and(Account::firstWhere('name', 'Reverse debt')->status)->toBe(AccountStatus::Inactive)
        ->and(Account::firstWhere('name', 'Cash')->status)->toBe(AccountStatus::Active)
        ->and(Category::count())->toBe(4)
        ->and(Transaction::count())->toBe(3);
});

test('opening balances and amounts arrive in minor units', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    expect(Account::firstWhere('name', 'DBBL')->initial_amount)->toBe(50000)
        ->and(Account::firstWhere('name', 'Cash')->initial_amount)->toBe(0)
        // Negative opening balances survive.
        ->and(Account::firstWhere('name', 'Reverse debt')->initial_amount)->toBe(-2948000);
});

test('records map onto the right transaction shape', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    $transfer = Transaction::where('type', TransactionType::Transfer)->first();
    $income = Transaction::where('type', TransactionType::Income)->first();
    $expense = Transaction::where('type', TransactionType::Expense)->first();

    expect($transfer->amount)->toBe(2500000)
        ->and($transfer->charge)->toBe(0)
        ->and($transfer->category_id)->toBeNull()
        ->and($transfer->fromAccount->name)->toBe('Boro vai')
        ->and($transfer->toAccount->name)->toBe('IFIC')
        ->and($transfer->note)->toBe('transfer')
        ->and($income->amount)->toBe(242000)
        ->and($income->to_account_id)->toBe(Account::firstWhere('name', 'Reverse debt')->id)
        ->and($income->from_account_id)->toBeNull()
        ->and($income->category->type)->toBe(CategoryType::Income)
        ->and($expense->from_account_id)->toBe(Account::firstWhere('name', 'Reverse debt')->id)
        ->and($expense->category->name)->toBe('Adjustment');
});

test('imported records keep their original date and time', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    // 1754672360692 ms.
    expect(Transaction::where('type', TransactionType::Transfer)->first()->created_at->timestamp)
        ->toBe(1754672360);
});

test('icons are matched against the shared library by export id', function () {
    $user = User::factory()->create();

    $accountIcon = Image::factory()->shared()->create([
        'type' => ImageType::Account,
        'image_name' => 'accounts/account_cash.png',
        'export_icon_id' => '301',
    ]);

    $categoryIcon = Image::factory()->shared()->create([
        'type' => ImageType::Category,
        'image_name' => 'categories/category_awards.png',
        'export_icon_id' => '101',
    ]);

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    // Sample: Cash carries icon 301, the "Awards" category carries 101.
    expect(Account::firstWhere('name', 'Cash')->icon_id)->toBe($accountIcon->id)
        ->and(Category::firstWhere('name', 'Awards')->icon_id)->toBe($categoryIcon->id)
        // Ids with nothing in the library stay blank.
        ->and(Account::firstWhere('name', 'DBBL')->icon_id)->toBeNull();
});

test('an icon id is never matched across image types', function () {
    $user = User::factory()->create();

    // 301 is an account icon in the sample; offering it as a category icon only.
    Image::factory()->shared()->create([
        'type' => ImageType::Category,
        'export_icon_id' => '301',
    ]);

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    expect(Account::firstWhere('name', 'Cash')->icon_id)->toBeNull();
});

test('a user owned image is never handed out by an import', function () {
    $user = User::factory()->create();

    Image::factory()->for($user)->create([
        'type' => ImageType::Account,
        'export_icon_id' => '301',
    ]);

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    expect(Account::firstWhere('name', 'Cash')->icon_id)->toBeNull();
});

test('balances are recalculated after import', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    // 0 opening, 25000.00 out.
    expect(Account::firstWhere('name', 'Boro vai')->amount)->toBe(-2500000)
        ->and(Account::firstWhere('name', 'IFIC')->amount)->toBe(2500000)
        // -29480.00 opening, +2420.00 income, -50000.00 expense.
        ->and(Account::firstWhere('name', 'Reverse debt')->amount)->toBe(-2948000 + 242000 - 5000000);
});

test('existing accounts and categories are reused rather than duplicated', function () {
    $user = User::factory()->create();
    $cash = Account::factory()->for($user)->create(['name' => 'cash', 'initial_amount' => 999, 'amount' => 999]);

    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    expect(Account::where('name', 'cash')->count())->toBe(1)
        ->and(Account::where('name', 'Cash')->count())->toBe(0)
        // A reused account keeps its own opening balance.
        ->and($cash->fresh()->initial_amount)->toBe(999);
});

test('the import lands in whichever book is active', function () {
    $user = User::factory()->create();
    $second = Book::factory()->for($user)->create();

    $this->actingAs($user)->post(route('books.switch', $second));
    $this->actingAs($user)->post(route('books.import.mbak'), ['backup' => mbakFile(samplePayload())]);

    expect(Account::withoutGlobalScopes()->where('book_id', $second->id)->count())->toBe(5)
        ->and(Account::withoutGlobalScopes()->where('book_id', $user->defaultBook()->id)->count())->toBe(0);
});

test('a file that is not a backup is rejected', function () {
    $user = User::factory()->create();

    $path = tempnam(sys_get_temp_dir(), 'junk').'.mbak';
    file_put_contents($path, 'not a backup');

    $this->actingAs($user)
        ->post(route('books.import.mbak'), ['backup' => new UploadedFile($path, 'junk.mbak', null, null, true)])
        ->assertSessionHasErrors('backup');

    expect(Account::count())->toBe(0);
});
