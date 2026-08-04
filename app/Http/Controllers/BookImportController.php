<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Lib\MbakFile;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Contact;
use App\Services\MbakExporter;
use App\Services\MbakImporter;
use App\Support\CurrentBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copies contacts and categories from the user's other books into the
 * active one, skipping anything whose name already exists here.
 */
class BookImportController extends Controller
{
    public function contacts(Request $request, CurrentBook $currentBook): JsonResponse
    {
        $existing = Contact::pluck('name')->map(fn ($name) => mb_strtolower($name))->all();

        $contacts = Contact::withoutGlobalScope('book')
            ->with(['book:id,name', 'picture'])
            ->whereIn('book_id', $this->otherBookIds($currentBook))
            ->orderBy('name')
            ->get()
            ->reject(fn (Contact $contact) => in_array(mb_strtolower($contact->name), $existing, true))
            ->unique(fn (Contact $contact) => mb_strtolower($contact->name))
            ->values()
            ->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'icon' => $contact->picture?->url,
                'book' => $contact->book?->name,
            ]);

        return response()->json(['data' => $contacts]);
    }

    public function categories(Request $request, CurrentBook $currentBook): JsonResponse
    {
        $existing = Category::get(['name', 'type'])
            ->map(fn (Category $category) => mb_strtolower($category->name).'|'.$category->type->value)
            ->all();

        $categories = Category::withoutGlobalScope('book')
            ->with(['book:id,name', 'icon'])
            ->whereIn('book_id', $this->otherBookIds($currentBook))
            ->orderBy('name')
            ->get()
            ->reject(fn (Category $category) => in_array(
                mb_strtolower($category->name).'|'.$category->type->value, $existing, true
            ))
            ->unique(fn (Category $category) => mb_strtolower($category->name).'|'.$category->type->value)
            ->values()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'type_label' => $category->type->label(),
                'icon' => $category->icon?->url,
                'book' => $category->book?->name,
            ]);

        return response()->json(['data' => $categories]);
    }

    public function storeContacts(Request $request, CurrentBook $currentBook): RedirectResponse
    {
        $ids = $this->selectedIds($request);

        $contacts = Contact::withoutGlobalScope('book')
            ->whereIn('book_id', $this->otherBookIds($currentBook))
            ->whereIn('id', $ids)
            ->get();

        DB::transaction(function () use ($contacts) {
            foreach ($contacts as $source) {
                if (Contact::whereRaw('lower(name) = ?', [mb_strtolower($source->name)])->exists()) {
                    continue;
                }

                $contact = Contact::create([
                    'name' => $source->name,
                    'phone' => $source->phone,
                    'email' => $source->email,
                    'picture_id' => $source->picture_id,
                ]);

                // Balances belong to the book, so the mirror account starts empty.
                $account = Account::create([
                    'type' => AccountType::Contact,
                    'status' => AccountStatus::Active,
                    'name' => $contact->name,
                    'initial_amount' => 0,
                    'amount' => 0,
                    'icon_id' => $source->picture_id,
                ]);

                $contact->update(['account_id' => $account->id]);
            }
        });

        return redirect()->route('contacts.index')->with('status', 'contacts-imported');
    }

    public function storeCategories(Request $request, CurrentBook $currentBook): RedirectResponse
    {
        $ids = $this->selectedIds($request);

        $categories = Category::withoutGlobalScope('book')
            ->whereIn('book_id', $this->otherBookIds($currentBook))
            ->whereIn('id', $ids)
            ->get();

        DB::transaction(function () use ($categories) {
            foreach ($categories as $source) {
                $exists = Category::whereRaw('lower(name) = ?', [mb_strtolower($source->name)])
                    ->where('type', $source->type)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Category::create([
                    'type' => $source->type,
                    'status' => $source->status,
                    'name' => $source->name,
                    'icon_id' => $source->icon_id,
                ]);
            }
        });

        return redirect()->route('categories.index')->with('status', 'categories-imported');
    }

    /**
     * Reads an .mbak backup exported by the mobile app.
     */
    public function mbak(Request $request, MbakImporter $importer): RedirectResponse
    {
        $request->validate([
            'backup' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('backup');

        if (strtolower((string) $file->getClientOriginalExtension()) !== 'mbak') {
            return back()->withErrors(['backup' => 'Pick a .mbak backup file.']);
        }

        try {
            $data = MbakFile::read((string) file_get_contents($file->getRealPath()));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        $summary = $importer->import($data);

        return redirect()->route('books.index')
            ->with('status', 'mbak-imported')
            ->with('mbak_summary', $summary);
    }

    /**
     * Writes the active book out as an .mbak the mobile app can restore.
     */
    public function exportMbak(MbakExporter $exporter, CurrentBook $currentBook): Response
    {
        $book = $currentBook->get();

        $filename = Str::slug($book?->name ?: 'book').'-'.now()->format('Y-m-d-His').'.mbak';

        return response($exporter->encrypted(), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function otherBookIds(CurrentBook $currentBook): array
    {
        return Book::where('id', '!=', $currentBook->id())->pluck('id')->all();
    }

    /**
     * @return array<int, int>
     */
    private function selectedIds(Request $request): array
    {
        return collect($request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'])->map(fn ($id) => (int) $id)->all();
    }
}
