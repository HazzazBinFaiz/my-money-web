<?php

namespace App\Models;

use App\Enums\ImageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Image extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'image_name'];

    protected function casts(): array
    {
        return [
            'type' => ImageType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Image $image) {
            if (is_null($image->user_id) && Auth::check()) {
                $image->user_id = Auth::id();
            }
        });

        // Own images plus shared (user_id = null) ones.
        static::addGlobalScope('usable', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where(function (Builder $query) {
                    $query->where('images.user_id', Auth::id())
                        ->orWhereNull('images.user_id');
                });
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType(Builder $query, ImageType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Shared images belong to nobody and cannot be edited or deleted.
     */
    public function isEditableBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function path(): string
    {
        return 'images/'.$this->image_name;
    }

    public function getUrlAttribute(): string
    {
        return route('images.show', $this);
    }
}
