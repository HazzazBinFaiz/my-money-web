<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the running balances of an account from its opening balance.
 *
 * Every write path (create, edit, delete, opening balance change) funnels
 * through here, so stored balances can never drift from the transactions.
 */
class LedgerService
{
    /**
     * Replays an account's transactions in date order, writing the running
     * balance onto each one and the closing balance onto the account.
     *
     * @param  iterable<int|Account|null>  $accounts
     */
    public function recalculate(iterable $accounts): void
    {
        $ids = collect($accounts)
            ->map(fn ($account) => $account instanceof Account ? $account->id : $account)
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->recalculateAccount($id);
            }
        });
    }

    public function recalculateAll(): void
    {
        $this->recalculate(Account::pluck('id'));
    }

    private function recalculateAccount(int $accountId): void
    {
        $account = Account::lockForUpdate()->find($accountId);

        if (! $account) {
            return;
        }

        $running = $account->initial_amount;

        $transactions = Transaction::where(function ($query) use ($accountId) {
            $query->where('from_account_id', $accountId)
                ->orWhere('to_account_id', $accountId);
        })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $running += $this->effectOn($transaction, $accountId);

            // Only the side this account sits on is touched; the other side
            // keeps whatever its own account's pass wrote.
            if ($transaction->from_account_id === $accountId) {
                $transaction->from_account_balance = $running;
            }

            if ($transaction->to_account_id === $accountId) {
                $transaction->to_account_balance = $running;
            }

            $transaction->saveQuietly();
        }

        $account->amount = $running;
        $account->saveQuietly();
    }

    /**
     * How a single transaction moves the given account.
     *
     * Money leaving an account carries the charge with it; a transfer credits
     * the destination with the amount only.
     */
    private function effectOn(Transaction $transaction, int $accountId): int
    {
        $effect = 0;

        if ($transaction->from_account_id === $accountId) {
            $effect -= $transaction->amount + $transaction->charge;
        }

        if ($transaction->to_account_id === $accountId) {
            $effect += $transaction->type === TransactionType::Income
                ? $transaction->amount - $transaction->charge
                : $transaction->amount;
        }

        return $effect;
    }
}
