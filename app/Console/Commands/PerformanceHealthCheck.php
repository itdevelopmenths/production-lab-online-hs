<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceHealthCheck extends Command
{
    protected $signature = 'app:health-check';

    protected $description = 'Periksa status performa dan konfigurasi lingkungan Laravel';

    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  🚀 Laravel Performance & Environment Health Check  ');
        $this->info('====================================================');

        // 1. Environment & Debug
        $env = config('app.env');
        $debug = config('app.debug');
        $this->line(sprintf(
            'Environment     : %s (Debug: %s)',
            $env === 'production' ? "<fg=green>{$env}</>" : "<fg=yellow>{$env}</>",
            $debug ? '<fg=red>ON (Matikan di production!)</>' : '<fg=green>OFF</>'
        ));

        // 2. Database Connection
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $duration = round((microtime(true) - $start) * 1000, 2);
            $driver = config('database.default');
            $this->line("Database        : <fg=green>Terhubung</> [{$driver}] ({$duration}ms)");
        } catch (\Throwable $e) {
            $this->line("Database        : <fg=red>Gagal ({$e->getMessage()})</>");
        }

        // 3. Cache Check
        $cacheStore = config('cache.default');
        try {
            $testKey = 'health_check_' . uniqid();
            Cache::put($testKey, 'ok', 10);
            $cacheVal = Cache::get($testKey);
            Cache::forget($testKey);
            $cacheStatus = ($cacheVal === 'ok') ? "<fg=green>OK</> [Store: {$cacheStore}]" : '<fg=red>Gagal</>';
            $this->line("Cache Driver    : {$cacheStatus}");
        } catch (\Throwable $e) {
            $this->line("Cache Driver    : <fg=red>Error ({$e->getMessage()})</> [Store: {$cacheStore}]");
        }

        // 4. Session & Queue
        $sessionDriver = config('session.driver');
        $queueDriver = config('queue.default');
        $this->line("Session Driver  : [{$sessionDriver}]");
        $this->line("Queue Driver    : [{$queueDriver}]");

        // 5. Config, Route, View Cache
        $configCached = file_exists(app()->getCachedConfigPath());
        $routeCached = file_exists(app()->getCachedRoutesPath());

        $this->line(sprintf(
            'Config Cached   : %s',
            $configCached ? '<fg=green>Ya (Aktif)</>' : '<fg=yellow>Belum (Jalankan config:cache di production)</>'
        ));
        $this->line(sprintf(
            'Routes Cached   : %s',
            $routeCached ? '<fg=green>Ya (Aktif)</>' : '<fg=yellow>Belum (Jalankan route:cache di production)</>'
        ));

        // 6. OPcache Status
        $opcacheEnabled = extension_loaded('Zend OPcache') && ini_get('opcache.enable');
        if ($opcacheEnabled) {
            $status = function_exists('opcache_get_status') ? opcache_get_status(false) : null;
            $freeMem = isset($status['memory_usage']['free_memory'])
                ? round($status['memory_usage']['free_memory'] / 1024 / 1024, 1) . 'MB free'
                : 'Active';
            $this->line("OPcache         : <fg=green>Aktif ({$freeMem})</>");
        } else {
            $this->line('OPcache         : <fg=yellow>Tidak Aktif (Sangat disarankan aktif di web hosting/production)</>');
        }

        $this->info('====================================================');
        return Command::SUCCESS;
    }
}
