<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\ClassPractice;
use App\Policies\ClassPracticePolicy;
use App\Models\QuickQuiz;
use App\Policies\QuickQuizPolicy;

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
        Gate::policy(ClassPractice::class, ClassPracticePolicy::class);
        Gate::policy(QuickQuiz::class, QuickQuizPolicy::class);
    }
}
