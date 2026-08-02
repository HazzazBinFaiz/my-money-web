<?php

namespace App\Support;

use App\Models\Book;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Resolves the book the request is working in.
 *
 * The choice lives in the session so switching books does not touch the
 * database. When access control arrives, the "may this user use this book"
 * check belongs here.
 */
class CurrentBook
{
    public const SESSION_KEY = 'current_book_id';

    private ?Book $book = null;

    /** Whose book the memo belongs to, so it can never leak between users. */
    private ?int $resolvedFor = null;

    public function get(): ?Book
    {
        if (! Auth::check()) {
            return null;
        }

        if ($this->book && $this->resolvedFor === Auth::id()) {
            return $this->book;
        }

        $id = Session::get(self::SESSION_KEY);

        $book = $id ? Book::find($id) : null;

        $book ??= Book::where('is_default', true)->first() ?? Book::orderBy('id')->first();

        if ($book) {
            Session::put(self::SESSION_KEY, $book->id);
        }

        $this->resolvedFor = Auth::id();

        return $this->book = $book;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function set(Book $book): void
    {
        Session::put(self::SESSION_KEY, $book->id);

        $this->book = $book;
        $this->resolvedFor = Auth::id();
    }

    /**
     * Drops the memo so the next resolve reads fresh state.
     */
    public function forget(): void
    {
        $this->book = null;
        $this->resolvedFor = null;

        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, Book>
     */
    public function available(): Collection
    {
        return Auth::check()
            ? Book::with('icon')->orderByDesc('is_default')->orderBy('name')->get()
            : collect();
    }
}
