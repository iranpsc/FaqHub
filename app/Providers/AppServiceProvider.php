<?php

namespace App\Providers;

use App\Models\Question;
use App\Observers\QuestionObserver;
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
        // Register Question observer for cache invalidation
        Question::observe(QuestionObserver::class);
    }
}
