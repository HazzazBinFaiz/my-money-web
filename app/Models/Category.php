<?php

namespace App\Models;

use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Models\Concerns\BelongsToBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use BelongsToBook, HasFactory;

    protected $fillable = ['user_id', 'book_id', 'type', 'status', 'name', 'icon_id'];

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'status' => CategoryStatus::class,
        ];
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'icon_id');
    }

    public function scopeOfType(Builder $query, CategoryType $type): Builder
    {
        return $query->where('type', $type);
    }
}
