<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda telah dinonaktifkan oleh Administrator.',
            ]);
        }

        // Validasi kesesuaian peran pengguna dengan rute yang diakses
        if (! empty($roles) && ! $user->hasRole($roles)) {
            abort(403, 'Akses Ditolak: Peran akun Anda ('.($user->role?->display_name ?? 'Tanpa Role').') tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
