<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsAuthenticated
{
    /**
     * Admin sessions expire after 8 hours (see config/wrbn.php).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            abort(401, 'Admin belum login.');
        }

        $loginAt = session('admin_login_at');
        $ttl = (int) config('wrbn.admin_session_minutes', 480);

        if (! is_numeric($loginAt) || now()->getTimestamp() - (int) $loginAt > $ttl * 60) {
            Auth::guard('admin')->logout();

            abort(401, 'Sesi admin tidak valid atau sudah berakhir.');
        }

        return $next($request);
    }
}
