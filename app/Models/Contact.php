<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = ['user_id', 'name', 'phone', 'email', 'picture_id', 'account_id'];

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'picture_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected static function booted(): void
    {
        // Renaming a contact renames its mirror account.
        static::updated(function (Contact $contact) {
            if ($contact->wasChanged('name') && $contact->account) {
                $contact->account->update(['name' => $contact->name]);
            }
        });
    }
}
