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

                // Granular Produk (menu Bahan Baku & Kemasan / Produk Jadi) <- exact | global produk
                'produk.bahan.view' => ['produk.bahan.view', 'produk.view'],
                'produk.bahan.create' => ['produk.bahan.create', 'produk.create'],
                'produk.bahan.edit' => ['produk.bahan.edit', 'produk.edit'],
                'produk.bahan.delete' => ['produk.bahan.delete', 'produk.delete'],
                'produk.produk_jadi.view' => ['produk.produk_jadi.view', 'produk.view'],
                'produk.produk_jadi.create' => ['produk.produk_jadi.create', 'produk.create'],
                'produk.produk_jadi.edit' => ['produk.produk_jadi.edit', 'produk.edit'],
                'produk.produk_jadi.delete' => ['produk.produk_jadi.delete', 'produk.delete'],

                // Module-level Produk <- global | salah satu kelompok
                'produk.view' => ['produk.view', 'produk.bahan.view', 'produk.produk_jadi.view'],
                'produk.create' => ['produk.create', 'produk.bahan.create', 'produk.produk_jadi.create'],
                'produk.edit' => ['produk.edit', 'produk.bahan.edit', 'produk.produk_jadi.edit'],
                'produk.delete' => ['produk.delete', 'produk.bahan.delete', 'produk.produk_jadi.delete'],

                // Kategori & Varian dulu menumpang izin produk.* (role lama tetap punya akses)
                'kategori.view' => ['kategori.view', 'produk.view'],
                'kategori.create' => ['kategori.create', 'produk.create'],
                'kategori.edit' => ['kategori.edit', 'produk.edit'],
                'kategori.delete' => ['kategori.delete', 'produk.delete'],
                'varian.view' => ['varian.view', 'produk.view'],
                'varian.create' => ['varian.create', 'produk.create'],
                'varian.edit' => ['varian.edit', 'produk.edit'],
                'varian.delete' => ['varian.delete', 'produk.delete'],

                // Klasifikasi ABC dulu menumpang izin analisa.*
                'klasifikasi_abc.view' => ['klasifikasi_abc.view', 'analisa.view'],
                'klasifikasi_abc.manage' => ['klasifikasi_abc.manage', 'analisa.manage'],

                // Log Pergerakan Stok dulu menumpang stok.view
                'stok.log.view' => ['stok.log.view', 'stok.view'],

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
