<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Observers\BookObserver;
use App\Observers\ReadingPlanObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Book::observe(BookObserver::class);
        ReadingPlan::observe(ReadingPlanObserver::class);
    }
}
