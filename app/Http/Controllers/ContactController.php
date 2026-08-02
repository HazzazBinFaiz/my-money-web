<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Account;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $contacts = Contact::with(['picture', 'account'])
            ->latest('id')
            ->get();

        return view('contacts.index', compact('contacts'));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $contact = Contact::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'picture_id' => $data['picture_id'] ?? null,
            ]);

            // Every contact carries a mirror account so it can take part in transactions.
            $account = Account::create([
                'type' => AccountType::Contact,
                'status' => AccountStatus::Active,
                'name' => $contact->name,
                'initial_amount' => $data['initial_amount'],
                'amount' => $data['initial_amount'],
                'icon_id' => $data['picture_id'] ?? null,
            ]);

            $contact->update(['account_id' => $account->id]);
        });

        return redirect()->route('contacts.index')->with('status', 'contact-created');
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($contact, $data) {
            $contact->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'picture_id' => $data['picture_id'] ?? null,
            ]);

            // Contact::updated keeps the account name in sync; balance and icon follow here.
            $account = $contact->account;

            if ($account) {
                $account->amount = $account->amount - $account->initial_amount + (int) $data['initial_amount'];
                $account->fill([
                    'initial_amount' => $data['initial_amount'],
                    'icon_id' => $data['picture_id'] ?? null,
                ])->save();
            }
        });

        return redirect()->route('contacts.index')->with('status', 'contact-updated');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        DB::transaction(function () use ($contact) {
            $account = $contact->account;
            $contact->delete();
            $account?->delete();
        });

        return redirect()->route('contacts.index')->with('status', 'contact-deleted');
    }
}
