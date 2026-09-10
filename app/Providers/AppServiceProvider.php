<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Force HTTPS in production / behind proxy
        if (config('app.env') !== 'local' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Manager bypass all gate checks (PRD: akses & approval seluruh menu)
        Gate::before(function ($user, $ability) {
            return $user->hasRole('manager') ? true : null;
        });

        // N+1 prevention in non-production
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(!app()->isProduction());
    }
}
