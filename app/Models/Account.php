<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Concerns\BelongsToBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    use BelongsToBook, HasFactory;

    protected $fillable = [
        'user_id', 'book_id', 'type', 'status', 'name', 'initial_amount', 'amount', 'icon_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
            'initial_amount' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'icon_id');
    }

    public function contact(): HasOne
    {
        return $this->hasOne(Contact::class);
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeAccounts(Builder $query): Builder
    {
        return $query->ofType(AccountType::Account);
    }

    public function scopeContacts(Builder $query): Builder
    {
        return $query->ofType(AccountType::Contact);
    }
}
