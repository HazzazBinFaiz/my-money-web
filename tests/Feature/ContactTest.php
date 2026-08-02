<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Contact;
use App\Models\User;

test('creating a contact also creates its mirror account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('contacts.store'), [
            'name' => 'Jane',
            'phone' => '0123',
            'email' => 'jane@example.com',
            'initial_amount' => 750,
        ])->assertRedirect(route('contacts.index'));

    $contact = Contact::first();
    $account = Account::first();

    expect($contact->account_id)->toBe($account->id)
        ->and($account->type)->toBe(AccountType::Contact)
        ->and($account->name)->toBe('Jane')
        ->and($account->initial_amount)->toBe(750)
        ->and($account->amount)->toBe(750);
});

test('renaming a contact renames its account', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('contacts.store'), ['name' => 'Jane', 'initial_amount' => 100]);

    $contact = Contact::first();

    $this->actingAs($user)
        ->put(route('contacts.update', $contact), ['name' => 'Jane Doe', 'initial_amount' => 100])
        ->assertRedirect();

    expect($contact->fresh()->account->name)->toBe('Jane Doe');
});

test('deleting a contact deletes its account', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('contacts.store'), ['name' => 'Jane', 'initial_amount' => 100]);

    $this->actingAs($user)->delete(route('contacts.destroy', Contact::first()))->assertRedirect();

    expect(Contact::count())->toBe(0)
        ->and(Account::count())->toBe(0);
});

test('contacts are scoped to their owner', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('contacts.store'), ['name' => 'Jane', 'initial_amount' => 100]);
    $contact = Contact::first();

    $this->actingAs(User::factory()->create())
        ->delete(route('contacts.destroy', $contact))
        ->assertNotFound();
});
