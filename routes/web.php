<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

    Route::get('/images', [ImageController::class, 'index'])->name('images.index');
    Route::post('/images', [ImageController::class, 'store'])->name('images.store');
    Route::get('/images/{image}', [ImageController::class, 'show'])->name('images.show');
    Route::delete('/images/{image}', [ImageController::class, 'destroy'])->name('images.destroy');
});

require __DIR__.'/auth.php';
