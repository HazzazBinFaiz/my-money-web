<?php

namespace App\Providers;

use App\Support\CurrentBook;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One resolver per request so the book is looked up once.
        $this->app->scoped(CurrentBook::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The layout and the book switcher need these on every page.
        View::composer(['layouts.navigation', 'layouts.app'], function ($view) {
            $currentBook = app(CurrentBook::class);

            $view->with([
                'currentBook' => $currentBook->get(),
                'availableBooks' => $currentBook->available(),
            ]);
        });
    }
}
