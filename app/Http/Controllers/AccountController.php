<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Lib\Util;
use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $accounts = Account::accounts()
            ->with('icon')
            ->latest('id')
            ->get();

        return view('accounts.index', compact('accounts'));
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $initialAmount = Util::toMinorUnits($data['initial_amount']);

        Account::create([
            'type' => AccountType::Account,
            'status' => AccountStatus::Active,
            'name' => $data['name'],
            'initial_amount' => $initialAmount,
            'amount' => $initialAmount,
            'icon_id' => $data['icon_id'] ?? null,
        ]);

        return redirect()->route('accounts.index')->with('status', 'account-created');
    }

    public function update(UpdateAccountRequest $request, Account $account, LedgerService $ledger): RedirectResponse
    {
        abort_unless($account->type === AccountType::Account, 404);

        $data = $request->validated();

        $account->fill([
            'name' => $data['name'],
            'initial_amount' => Util::toMinorUnits($data['initial_amount']),
            'status' => AccountStatus::from((int) $data['status']),
            'icon_id' => $data['icon_id'] ?? null,
        ])->save();

        // A new opening balance shifts every balance recorded after it.
        $ledger->recalculate([$account->id]);

        return redirect()->route('accounts.index')->with('status', 'account-updated');
    }

    public function destroy(Account $account): RedirectResponse
    {
        abort_unless($account->type === AccountType::Account, 404);

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'account-deleted');
    }
}
