<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonInterface;

/**
 * Writes transactions and hands the balance work to the ledger.
 *
 * Callers are responsible for wrapping this in a database transaction.
 */
class TransactionRecorder
{
    public function __construct(private LedgerService $ledger) {}

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
        $transaction = Transaction::create(
            $this->attributes($type, $accountId, $counterpartId, $categoryId, $amount, $charge, $note, $occurredAt)
        );

        $this->ledger->recalculate([$accountId, $counterpartId]);

        return $transaction->refresh();
    }

    /**
     * Writes a batch, then replays every account it touched once rather than
     * once per row.
     *
     * @param  list<array{type: TransactionType, account_id: int, to_account_id: ?int, category_id: ?int, amount: int, charge: int, note: ?string, occurred_at: CarbonInterface}>  $rows
     */
    public function recordMany(array $rows): void
    {
        $touched = [];

        foreach ($rows as $row) {
            Transaction::create($this->attributes(
                $row['type'], $row['account_id'], $row['to_account_id'], $row['category_id'],
                $row['amount'], $row['charge'], $row['note'], $row['occurred_at'],
            ));

            $touched[] = $row['account_id'];
            $touched[] = $row['to_account_id'];
        }

        $this->ledger->recalculate($touched);
    }

    /**
     * Applies an edit, replaying both the accounts it used to touch and the
     * ones it touches now.
     */
    public function update(
        Transaction $transaction,
        TransactionType $type,
        int $accountId,
        ?int $counterpartId,
        ?int $categoryId,
        int $amount,
        int $charge,
        ?string $note,
        CarbonInterface $occurredAt,
    ): Transaction {
        $touched = [$transaction->from_account_id, $transaction->to_account_id, $accountId, $counterpartId];

        $transaction->update(
            $this->attributes($type, $accountId, $counterpartId, $categoryId, $amount, $charge, $note, $occurredAt)
        );

        $this->ledger->recalculate($touched);

        return $transaction->refresh();
    }

    public function delete(Transaction $transaction): void
    {
        $touched = [$transaction->from_account_id, $transaction->to_account_id];

        $transaction->delete();

        $this->ledger->recalculate($touched);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(
        TransactionType $type,
        int $accountId,
        ?int $counterpartId,
        ?int $categoryId,
        int $amount,
        int $charge,
        ?string $note,
        CarbonInterface $occurredAt,
    ): array {
        return [
            'type' => $type,
            'category_id' => $type === TransactionType::Transfer ? null : $categoryId,
            'amount' => $amount,
            'charge' => $charge,
            'from_account_id' => $type === TransactionType::Income ? null : $accountId,
            'to_account_id' => match ($type) {
                TransactionType::Income => $accountId,
                TransactionType::Transfer => $counterpartId,
                default => null,
            },
            'note' => $note,
            'created_at' => $occurredAt,
        ];
    }
}
