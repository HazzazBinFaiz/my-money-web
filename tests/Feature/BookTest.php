<?php

use App\Enums\CurrencyPosition;
use App\Lib\Util;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Image;
use App\Models\User;
use App\Support\CurrentBook;

test('registering seeds a default book named after the user', function () {
    $this->post(route('register'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $book = Book::withoutGlobalScopes()->where('name', "Ada's Book")->first();

    expect($book)->not->toBeNull()
        ->and($book->is_default)->toBeTrue()
        ->and($book->decimal_places)->toBe(2);
});

test('records are scoped to the active book', function () {
    $user = User::factory()->create();
    $first = $user->defaultBook();
    $second = Book::factory()->for($user)->create(['name' => 'Business']);

    $this->actingAs($user);

    Account::factory()->for($user)->create(['book_id' => $first->id, 'name' => 'Personal Cash']);
    Account::factory()->for($user)->create(['book_id' => $second->id, 'name' => 'Business Cash']);

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertSee('Personal Cash')
        ->assertDontSee('Business Cash');

    $this->actingAs($user)->post(route('books.switch', $second))->assertRedirect();

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertSee('Business Cash')
        ->assertDontSee('Personal Cash');
});

test('new records land in the active book', function () {
    $user = User::factory()->create();
    $second = Book::factory()->for($user)->create();

    $this->actingAs($user)->post(route('books.switch', $second));

    $this->actingAs($user)->post(route('accounts.store'), ['name' => 'Cash', 'initial_amount' => '10']);

    expect(Account::withoutGlobalScopes()->where('name', 'Cash')->value('book_id'))->toBe($second->id);
});

test('book preferences drive amount formatting', function () {
    $user = User::factory()->create();
    $book = $user->defaultBook();

    $this->actingAs($user)->put(route('books.update', $book), [
        'name' => 'Personal',
        'decimal_places' => 0,
        'currency' => 'BDT',
        'currency_position' => CurrencyPosition::After->value,
    ])->assertRedirect(route('books.index'));

    app(CurrentBook::class)->forget();
    $book->refresh();

    expect(Util::displayAmount(123456, $book))->toBe('1,235 BDT')
        ->and(Util::displayAmount(123456, $book->fill(['currency_position' => CurrencyPosition::Before])))->toBe('BDT1,235')
        ->and(Util::displayAmount(123456, $book->fill(['decimal_places' => 2])))->toBe('BDT1,234.56')
        ->and(Util::displayAmount(123456, $book->fill(['currency' => null])))->toBe('1,234.56');
});

test('a book is deleted only when its name is retyped', function () {
    $user = User::factory()->create();
    $book = Book::factory()->for($user)->create(['name' => 'Business']);

    $this->actingAs($user)->delete(route('books.destroy', $book), ['name' => 'wrong'])
        ->assertSessionHasErrors('name');

    expect(Book::withoutGlobalScopes()->count())->toBe(2);

    $this->actingAs($user)->delete(route('books.destroy', $book), ['name' => 'Business'])
        ->assertRedirect(route('books.index'));

    expect(Book::withoutGlobalScopes()->count())->toBe(1);
});

test('the last book cannot be deleted', function () {
    $user = User::factory()->create();
    $book = $user->defaultBook();

    $this->actingAs($user)->delete(route('books.destroy', $book), ['name' => $book->name])
        ->assertSessionHasErrors('name');

    expect(Book::withoutGlobalScopes()->count())->toBe(1);
});

test('another users book cannot be switched to or edited', function () {
    $user = User::factory()->create();
    $foreign = Book::factory()->create();

    $this->actingAs($user)->post(route('books.switch', $foreign))->assertNotFound();
    $this->actingAs($user)->put(route('books.update', $foreign), [
        'name' => 'Taken',
        'decimal_places' => 2,
        'currency_position' => CurrencyPosition::Before->value,
    ])->assertNotFound();
});

test('import lists only names missing from the active book', function () {
    $user = User::factory()->create();
    $source = Book::factory()->for($user)->create(['name' => 'Old book']);

    Contact::factory()->for($user)->create(['book_id' => $source->id, 'name' => 'Jane']);
    Contact::factory()->for($user)->create(['book_id' => $source->id, 'name' => 'John']);
    Contact::factory()->for($user)->create(['book_id' => $user->defaultBook()->id, 'name' => 'Jane']);

    $names = $this->actingAs($user)->getJson(route('books.import.contacts'))
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['John']);
});

test('importing contacts copies them with a fresh mirror account', function () {
    $user = User::factory()->create();
    $source = Book::factory()->for($user)->create();

    $contact = Contact::factory()->for($user)->create([
        'book_id' => $source->id,
        'name' => 'Jane',
        'phone' => '0123',
    ]);

    $this->actingAs($user)->post(route('books.import.contacts.store'), ['ids' => [$contact->id]])
        ->assertRedirect(route('contacts.index'));

    $imported = Contact::firstWhere('name', 'Jane');

    expect($imported)->not->toBeNull()
        ->and($imported->book_id)->toBe($user->defaultBook()->id)
        ->and($imported->phone)->toBe('0123')
        ->and($imported->account->amount)->toBe(0);
});

test('importing categories skips names already present', function () {
    $user = User::factory()->create();
    $source = Book::factory()->for($user)->create();
    $current = $user->defaultBook();

    $groceries = Category::factory()->for($user)->create(['book_id' => $source->id, 'name' => 'Groceries']);
    Category::factory()->for($user)->create([
        'book_id' => $current->id,
        'name' => 'Groceries',
        'type' => $groceries->type,
    ]);

    $this->actingAs($user)->post(route('books.import.categories.store'), ['ids' => [$groceries->id]])
        ->assertRedirect(route('categories.index'));

    expect(Category::where('name', 'Groceries')->count())->toBe(1);
});

test('a book icon must come from the book library', function () {
    $user = User::factory()->create();
    $book = $user->defaultBook();

    $accountIcon = Image::factory()->for($user)->create();
    $bookIcon = Image::factory()->for($user)->book()->create();

    $payload = [
        'name' => 'Personal',
        'decimal_places' => 2,
        'currency_position' => CurrencyPosition::Before->value,
    ];

    $this->actingAs($user)
        ->put(route('books.update', $book), $payload + ['icon_id' => $accountIcon->id])
        ->assertSessionHasErrors('icon_id');

    $this->actingAs($user)
        ->put(route('books.update', $book), $payload + ['icon_id' => $bookIcon->id])
        ->assertSessionHasNoErrors();

    expect($book->fresh()->icon_id)->toBe($bookIcon->id);
});

test('the books page renders with the active book highlighted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('books.index'))
        ->assertOk()
        ->assertSee($user->defaultBook()->name)
        ->assertSee('Active');
});
