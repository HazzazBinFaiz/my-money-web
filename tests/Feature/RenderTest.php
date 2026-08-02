<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;

test('all pages render with rows', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create();
    Category::factory()->for($user)->create();
    $account = Account::factory()->for($user)->contact()->create();
    Contact::factory()->for($user)->create(['account_id' => $account->id]);

    $this->actingAs($user)->get(route('accounts.index'))->assertOk();
    $this->actingAs($user)->get(route('contacts.index'))->assertOk();
    $this->actingAs($user)->get(route('categories.index'))->assertOk();
});
