<?php

namespace App\Http\Middleware;

use App\Services\Qa\QaClock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyQaClock
{
    public function handle(Request $request, Closure $next): Response
    {
        app(QaClock::class)->apply();

        return $next($request);
    }
}
