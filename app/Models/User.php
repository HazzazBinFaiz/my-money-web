<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Every user starts with one book; everything else hangs off it.
     */
    protected static function booted(): void
    {
        static::created(fn (User $user) => Book::createDefaultFor($user));
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * The book the user lands in when nothing else is chosen.
     */
    public function defaultBook(): ?Book
    {
        return $this->books()->orderByDesc('is_default')->orderBy('id')->first();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
