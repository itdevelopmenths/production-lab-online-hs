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
            if ($user->hasRole('manager')) {
                return true;
            }

            // Dual compatibility for Request & Transfer aliases with legacy RT permissions
            if (str_starts_with($ability, 'request.')) {
                $sub = substr($ability, 8);
                $rtMap = ['view' => 'rt.view', 'create' => 'rt.create', 'submit' => 'rt.submit', 'approve' => 'rt.approve', 'cancel' => 'rt.cancel'];
                if (isset($rtMap[$sub]) && $user->can($rtMap[$sub])) {
                    return true;
                }
            }
            if (str_starts_with($ability, 'transfer.')) {
                $sub = substr($ability, 9);
                $rtMap = ['view' => 'rt.view', 'create' => 'rt.create', 'ship' => 'rt.ship', 'receive' => 'rt.receive', 'cancel' => 'rt.cancel'];
                if (isset($rtMap[$sub]) && $user->can($rtMap[$sub])) {
                    return true;
                }
            }

            return null;
        });

        // N+1 prevention in non-production
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(!app()->isProduction());

        // Slow query monitoring in local development (> 100ms)
        if ($this->app->environment('local')) {
            \Illuminate\Support\Facades\DB::listen(function ($query) {
                if ($query->time > 100) {
                    \Illuminate\Support\Facades\Log::warning('SLOW QUERY DETECTED', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                    ]);
                }
            });
        }
    }
}
