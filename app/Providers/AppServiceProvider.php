<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        // Pilot consent must not share a throttle bucket with QQ start/answer,
        // password/profile operations, or other numeric "throttle:5,1" routes.
        // Preserve the five-per-minute ceiling; isolate its per-user key.
        RateLimiter::for('pilot-legal-consent', static function (Request $request): Limit {
            $actor = $request->user();
            $scope = $actor ? 'user:'.$actor->getAuthIdentifier() : 'ip:'.$request->ip();
            return Limit::perMinute(5)->by($scope);
        });
    }
}
