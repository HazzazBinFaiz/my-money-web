<?php

namespace App\Models;

use App\Enums\CurrencyPosition;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ledger of its own: accounts, contacts, categories and transactions all
 * belong to exactly one book.
 *
 * Access is owner based for now; book level access control will be layered
 * on here rather than on the scoped models.
 */
class Book extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id', 'name', 'icon_id', 'is_default',
        'decimal_places', 'currency', 'currency_position',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'decimal_places' => 'integer',
            'currency_position' => CurrencyPosition::class,
        ];
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'icon_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The book every new user starts with.
     */
    public static function createDefaultFor(User $user): self
    {
        return static::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => $user->name."'s Book",
            'is_default' => true,
        ]);
    }
}
