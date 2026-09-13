<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionSecurityController extends Controller
{
    /**
     * Heartbeat / Ping endpoint to verify session validity and refresh CSRF token.
     * Prevents unexpected session termination and raw unauthorized alerts.
     */
    public function ping(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'authenticated' => false,
                'message' => 'Sesi telah kedaluwarsa. Silakan login kembali.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        return response()->json([
            'authenticated' => true,
            'csrf_token' => csrf_token(),
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->roleName(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
