<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id', 'type', 'category_id', 'amount', 'charge',
        'from_account_id', 'to_account_id', 'balance', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
            'charge' => 'integer',
            'balance' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function scopeOfType(Builder $query, TransactionType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * The account whose balance the transaction is reported against.
     */
    public function primaryAccount(): ?Account
    {
        return $this->type === TransactionType::Income
            ? $this->toAccount
            : $this->fromAccount;
    }

    public function label(): string
    {
        return match ($this->type) {
            TransactionType::Income => $this->toAccount?->name ?? '—',
            TransactionType::Expense => $this->fromAccount?->name ?? '—',
            TransactionType::Transfer => sprintf(
                '%s → %s',
                $this->fromAccount?->name ?? '—',
                $this->toAccount?->name ?? '—',
            ),
        };
    }

    public function title(): string
    {
        return $this->type === TransactionType::Transfer
            ? 'Transfer'
            : ($this->category?->name ?? '—');
    }
}
