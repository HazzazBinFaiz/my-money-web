<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;

/**
 * Applies a transaction to the account balances and stores the row.
 *
 * Callers are responsible for wrapping this in a database transaction.
 */
class TransactionRecorder
{
    public function record(
        TransactionType $type,
        int $accountId,
        ?int $counterpartId,
        ?int $categoryId,
        int $amount,
        int $charge,
        ?string $note,
        CarbonInterface $occurredAt,
    ): Transaction {
        $account = Account::lockForUpdate()->findOrFail($accountId);

        $counterpart = $type === TransactionType::Transfer
            ? Account::lockForUpdate()->findOrFail($counterpartId)
            : null;

        // A charge is always paid out of the account the money leaves,
        // which for income is the receiving account itself.
        if ($type === TransactionType::Income) {
            $account->amount += $amount - $charge;
        } else {
            $account->amount -= $amount + $charge;
        }

        $account->save();

        if ($counterpart) {
            $counterpart->amount += $amount;
            $counterpart->save();
        }

        return Transaction::create([
            'type' => $type,
            'category_id' => $type === TransactionType::Transfer ? null : $categoryId,
            'amount' => $amount,
            'charge' => $charge,
            'from_account_id' => $type === TransactionType::Income ? null : $account->id,
            'to_account_id' => match ($type) {
                TransactionType::Income => $account->id,
                TransactionType::Transfer => $counterpart->id,
                default => null,
            },
            // Closing balance of the account the transaction is reported against.
            'balance' => $account->amount,
            'note' => $note,
            'created_at' => $occurredAt,
        ]);
    }
}
