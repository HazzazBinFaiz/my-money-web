<?php

namespace App\Models\Concerns;

use App\Models\Book;
use App\Models\User;
use App\Support\CurrentBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes a model to the active book and stamps book_id and user_id on create.
 *
 * Everything the user sees is filtered here, so a book switch changes the
 * whole application without any controller knowing about it.
 */
trait BelongsToBook
{
    public static function bootBelongsToBook(): void
    {
        static::creating(function ($model) {
            if (is_null($model->user_id) && Auth::check()) {
                $model->user_id = Auth::id();
            }

            if (is_null($model->book_id)) {
                $model->book_id = app(CurrentBook::class)->id();
            }
        });

        static::addGlobalScope('book', function (Builder $builder) {
            $bookId = app(CurrentBook::class)->id();

            if ($bookId) {
                $builder->where($builder->getModel()->qualifyColumn('book_id'), $bookId);
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
