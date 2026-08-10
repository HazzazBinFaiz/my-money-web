<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookImportController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::post('/contact', [SiteController::class, 'contact'])
    ->middleware('throttle:5,1')
    ->name('site.contact');
Route::get('/privacy', [SiteController::class, 'privacy'])->name('privacy');
Route::get('/terms', [SiteController::class, 'terms'])->name('terms');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('transactions/bulk', [TransactionController::class, 'bulk'])->name('transactions.bulk');
    Route::post('transactions/bulk', [TransactionController::class, 'storeBulk'])->name('transactions.bulk.store');
    Route::resource('transactions', TransactionController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::resource('books', BookController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('books/{book}/switch', [BookController::class, 'switch'])->name('books.switch');

    Route::get('books/import/contacts', [BookImportController::class, 'contacts'])->name('books.import.contacts');
    Route::post('books/import/contacts', [BookImportController::class, 'storeContacts'])->name('books.import.contacts.store');
    Route::get('books/import/categories', [BookImportController::class, 'categories'])->name('books.import.categories');
    Route::post('books/import/categories', [BookImportController::class, 'storeCategories'])->name('books.import.categories.store');

    Route::post('books/import/mbak', [BookImportController::class, 'mbak'])->name('books.import.mbak');
    Route::get('books/export/mbak', [BookImportController::class, 'exportMbak'])->name('books.export.mbak');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('accounts', [ReportController::class, 'accounts'])->name('accounts');
        Route::get('accounts/{account}', [ReportController::class, 'account'])->name('accounts.detail');
        Route::get('categories', [ReportController::class, 'categories'])->name('categories');
        Route::get('categories/{category}', [ReportController::class, 'category'])->name('categories.detail');
    });

    // Served under /media because public/images holds the seeded icon files,
    // and the web server would answer /images from disk instead of the app.
    Route::get('/media', [ImageController::class, 'index'])->name('images.index');
    Route::post('/media', [ImageController::class, 'store'])->name('images.store');
    Route::get('/media/{image}', [ImageController::class, 'show'])->name('images.show');
    Route::delete('/media/{image}', [ImageController::class, 'destroy'])->name('images.destroy');
});

require __DIR__.'/auth.php';
