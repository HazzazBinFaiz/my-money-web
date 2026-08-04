<?php

namespace App\Http\Controllers;

use App\Enums\CurrencyPosition;
use App\Enums\ImageType;
use App\Models\Book;
use App\Support\CurrentBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(CurrentBook $currentBook): View
    {
        return view('books.index', [
            'books' => Book::with('icon')->withCount(['accounts', 'transactions'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'current' => $currentBook->get(),
        ]);
    }

    public function store(Request $request, CurrentBook $currentBook): RedirectResponse
    {
        $data = $this->validated($request);

        $book = Book::create($data + ['is_default' => false]);

        // A brand new book is almost always the one you want to work in.
        $currentBook->set($book);

        return redirect()->route('books.index')->with('status', 'book-created');
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        $book->update($this->validated($request));

        return redirect()->route('books.index')->with('status', 'book-updated');
    }

    /**
     * Switching book changes what every other page shows.
     */
    public function switch(Book $book, CurrentBook $currentBook): RedirectResponse
    {
        $currentBook->set($book);

        return back()->with('status', 'book-switched');
    }

    /**
     * Deleting takes the whole ledger with it, so the name must be retyped.
     */
    public function destroy(Request $request, Book $book, CurrentBook $currentBook): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', Rule::in([$book->name])],
        ], [
            'name.in' => 'Type the book name exactly to confirm.',
        ]);

        if (Book::count() <= 1) {
            return back()->withErrors(['name' => 'Your last book cannot be deleted.']);
        }

        $wasCurrent = $currentBook->id() === $book->id;

        $book->delete();

        if ($wasCurrent) {
            $currentBook->forget();
        }

        return redirect()->route('books.index')->with('status', 'book-deleted');
    }

    /**
     * The icon must be one of the user's own images, or a shared one.
     */
    private function usableIconRule(Request $request): Exists
    {
        $userId = $request->user()?->id;

        return Rule::exists('images', 'id')
            ->where('type', ImageType::Book->value)
            ->where(fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon_id' => ['nullable', 'integer', $this->usableIconRule($request)],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:2'],
            'currency' => ['nullable', 'string', 'max:8'],
            'currency_position' => ['required', 'integer', Rule::enum(CurrencyPosition::class)],
        ]);
    }
}
