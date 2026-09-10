<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoleSwitcherController extends Controller
{
    public const ALLOWED_ROLES = [
        'manager',
        'purchasing',
        'gudang',
        'operasional',
        'fulfillment',
    ];

    /**
     * Switch current user role for local development and testing.
     */
    public function switch(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Role switcher hanya diizinkan pada environment local atau testing.');

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
        ]);

        $role = $data['role'];

        $user = User::where('email', "{$role}@heavenscent.id")->first()
            ?? User::role($role)->first();

        if (! $user) {
            return back()->with('error', "User dengan peran '{$role}' tidak ditemukan.");
        }

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Beralih peran ke: ' . ucfirst($role) . ' (' . $user->name . ')');
    }
}
