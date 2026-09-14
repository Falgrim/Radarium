<?php

namespace App\Providers;

use App\Models\User;
use App\Services\BuilderSpecialityMatcher;
use App\Services\RussianRegionNormalizer;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BuilderSpecialityMatcher::class, static fn () => new BuilderSpecialityMatcher(null));
        $this->app->singleton(RussianRegionNormalizer::class, static fn () => RussianRegionNormalizer::withDefaultPaths());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;
            if ($user instanceof User) {
                $updates = ['last_login_at' => now()];
                if (is_null($user->first_login_at)) {
                    $updates['first_login_at'] = now();
                }
                $user->forceFill($updates)->saveQuietly();

                if ($user->isTrialExpired() && !$user->hasActivePaidTariff()) {
                    session()->flash('show_trial_expired_modal', true);
                }
            }
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip())->response(function (Request $request, array $headers) {
                return response(['status' => 429, 'error' => 'Too Many Requests'], 429, $headers);
            });
        });

        Paginator::useBootstrapFive();
        Paginator::useBootstrapFour();
    }
}
