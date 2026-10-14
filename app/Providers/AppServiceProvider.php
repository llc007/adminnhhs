<?php

namespace App\Providers;

use App\Listeners\FilterNotificationEmails;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();

        Event::listen(
            NotificationSending::class,
            FilterNotificationEmails::class
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        if (
            app()->isProduction()
            || str_starts_with((string) config('app.url'), 'https://')
            || str_contains(request()->getHost(), 'nhhs.cl')
            || request()->isSecure()
            || request()->header('X-Forwarded-Proto') === 'https'
        ) {
            if (! app()->environment('testing')) {
                URL::forceScheme('https');
            }
        }

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
