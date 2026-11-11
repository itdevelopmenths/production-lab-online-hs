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
            if (method_exists($user, 'hasRole') && $user->hasRole('manager')) {
                return true;
            }

            if (! is_string($ability)) {
                return null;
            }

            static $activeChecks = [];
            if (isset($activeChecks[$ability])) {
                return null;
            }

            $abilityAliases = [
                // Granular Request <- exact | global request | legacy rt
                'request.bahan.create' => ['request.bahan.create', 'request.create', 'rt.create'],
                'request.produk_jadi.create' => ['request.produk_jadi.create', 'request.create', 'rt.create'],

                // Granular Transfer <- exact | global transfer | legacy rt
                'transfer.bahan.create' => ['transfer.bahan.create', 'transfer.create', 'rt.create'],
                'transfer.bahan.ship' => ['transfer.bahan.ship', 'transfer.ship', 'rt.ship'],
                'transfer.bahan.receive' => ['transfer.bahan.receive', 'transfer.receive', 'rt.receive'],
                'transfer.produk_jadi.create' => ['transfer.produk_jadi.create', 'transfer.create', 'rt.create'],
                'transfer.produk_jadi.ship' => ['transfer.produk_jadi.ship', 'transfer.ship', 'rt.ship'],
                'transfer.produk_jadi.receive' => ['transfer.produk_jadi.receive', 'transfer.receive', 'rt.receive'],

                // Module-level Request (never leaks into Transfer)
                'request.view' => ['request.view', 'rt.view'],
                'request.create' => ['request.create', 'request.bahan.create', 'request.produk_jadi.create', 'rt.create'],
                'request.submit' => ['request.submit', 'rt.submit'],
                'request.approve' => ['request.approve', 'rt.approve'],
                'request.cancel' => ['request.cancel', 'rt.cancel'],

                // Module-level Transfer (never leaks from Request)
                'transfer.view' => ['transfer.view', 'rt.view'],
                'transfer.create' => ['transfer.create', 'transfer.bahan.create', 'transfer.produk_jadi.create', 'rt.create'],
                'transfer.ship' => ['transfer.ship', 'transfer.bahan.ship', 'transfer.produk_jadi.ship', 'rt.ship'],
                'transfer.receive' => ['transfer.receive', 'transfer.bahan.receive', 'transfer.produk_jadi.receive', 'rt.receive'],
                'transfer.cancel' => ['transfer.cancel', 'rt.cancel'],

                // Legacy rt.* compatibility
                'rt.view' => ['rt.view', 'request.view', 'transfer.view'],
                'rt.create' => [
                    'rt.create',
                    'request.create', 'request.bahan.create', 'request.produk_jadi.create',
                    'transfer.create', 'transfer.bahan.create', 'transfer.produk_jadi.create',
                ],
                'rt.submit' => ['rt.submit', 'request.submit'],
                'rt.approve' => ['rt.approve', 'request.approve'],
                'rt.ship' => ['rt.ship', 'transfer.ship', 'transfer.bahan.ship', 'transfer.produk_jadi.ship'],
                'rt.receive' => ['rt.receive', 'transfer.receive', 'transfer.bahan.receive', 'transfer.produk_jadi.receive'],
                'rt.cancel' => ['rt.cancel', 'request.cancel', 'transfer.cancel'],
            ];

            if (! isset($abilityAliases[$ability])) {
                return null;
            }

            $activeChecks[$ability] = true;
            try {
                foreach ($abilityAliases[$ability] as $candidate) {
                    $matched = method_exists($user, 'checkPermissionTo')
                        ? $user->checkPermissionTo($candidate)
                        : $user->can($candidate);

                    if ($matched) {
                        return true;
                    }
                }
            } finally {
                unset($activeChecks[$ability]);
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
