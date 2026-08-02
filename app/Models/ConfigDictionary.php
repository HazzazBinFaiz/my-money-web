<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBook;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigDictionary extends Model
{
    use BelongsToBook, HasFactory;

    protected $fillable = ['user_id', 'book_id', 'key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
