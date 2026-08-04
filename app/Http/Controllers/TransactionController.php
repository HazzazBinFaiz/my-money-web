<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Http\Requests\StoreBulkTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->query('view') === 'table' ? 'table' : 'cards';

        $filters = $request->validate([
            'account' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
        ]);

        $accountId = $filters['account'] ?? null;
        $categoryId = $filters['category'] ?? null;

        $transactions = Transaction::with([
            'category.icon',
            'fromAccount.icon',
            'toAccount.icon',
        ])
            // An account matches whichever side of the transaction it sits on.
            ->when($accountId, fn ($query, $id) => $query->where(
                fn ($sides) => $sides->where('from_account_id', $id)->orWhere('to_account_id', $id)
            ))
            ->when($categoryId, fn ($query, $id) => $query->where('category_id', $id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'view' => $view,
            'accountId' => $accountId,
            'categoryId' => $categoryId,
            'filterAccounts' => Account::orderBy('type')->orderBy('name')->get(),
            'filterCategories' => Category::orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('transactions.create', $this->formOptions());
    }

    /**
     * Active accounts and categories, grouped the way both entry forms need them.
     *
     * @return array<string, Collection>
     */
    private function formOptions(): array
    {
        $accounts = Account::with('icon')
            ->where('status', AccountStatus::Active)
            ->orderBy('name')
            ->get();

        $categories = Category::with('icon')
            ->where('status', CategoryStatus::Active)
            ->orderBy('name')
            ->get();

        return [
            'ownAccounts' => $accounts->where('type', AccountType::Account)->values(),
            'contactAccounts' => $accounts->where('type', AccountType::Contact)->values(),
            'incomeCategories' => $categories->where('type', CategoryType::Income)->values(),
            'expenseCategories' => $categories->where('type', CategoryType::Expense)->values(),
        ];
    }

    /**
     * Spreadsheet style entry for many transactions at once.
     */
    public function bulk(): View
    {
        return view('transactions.bulk', $this->formOptions());
    }

    public function storeBulk(StoreBulkTransactionRequest $request, TransactionRecorder $recorder): RedirectResponse
    {
        $rows = $request->transactionRows();

        // All or nothing: a bad row must not leave half the batch applied.
        DB::transaction(fn () => $recorder->recordMany($rows));

        return redirect()->route('transactions.index')
            ->with('status', 'transactions-created')
            ->with('created_count', count($rows));
    }

    public function store(StoreTransactionRequest $request, TransactionRecorder $recorder): RedirectResponse
    {
        DB::transaction(fn () => $recorder->record(
            type: $request->transactionType(),
            accountId: $request->integer('account_id'),
            counterpartId: $request->integer('to_account_id') ?: null,
            categoryId: $request->integer('category_id') ?: null,
            amount: $request->amountInMinorUnits(),
            charge: $request->chargeInMinorUnits(),
            note: $request->input('note'),
            occurredAt: $request->occurredAt(),
        ));

        return redirect()->route('transactions.index')->with('status', 'transaction-created');
    }

    public function edit(Transaction $transaction): View
    {
        return view('transactions.edit', $this->formOptions() + ['transaction' => $transaction]);
    }

    public function update(
        StoreTransactionRequest $request,
        Transaction $transaction,
        TransactionRecorder $recorder,
    ): RedirectResponse {
        DB::transaction(fn () => $recorder->update(
            transaction: $transaction,
            type: $request->transactionType(),
            accountId: $request->integer('account_id'),
            counterpartId: $request->integer('to_account_id') ?: null,
            categoryId: $request->integer('category_id') ?: null,
            amount: $request->amountInMinorUnits(),
            charge: $request->chargeInMinorUnits(),
            note: $request->input('note'),
            occurredAt: $request->occurredAt(),
        ));

        return redirect()->route('transactions.index')->with('status', 'transaction-updated');
    }

    public function destroy(Transaction $transaction, TransactionRecorder $recorder): RedirectResponse
    {
        // The ledger replays the affected accounts, so later balances stay right.
        DB::transaction(fn () => $recorder->delete($transaction));

        return redirect()->route('transactions.index')->with('status', 'transaction-deleted');
    }
}
