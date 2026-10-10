<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates admin endpoints by the role stored on the admin account.
 *
 *   superadmin  everything, including deleting clients and managing the
 *               member whitelist
 *   admin       day-to-day operations: projects, stage, assignments,
 *               requests, payments, messages
 *   support     read-only
 */
class RequireAdminRole
{
    /**
     * @param  string  ...$roles  roles allowed through, e.g. admin.role:admin,superadmin
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            abort(401, 'Admin belum login.');
        }

        if (! in_array((string) $admin->role, $roles, true)) {
            abort(403, 'Role Anda tidak punya akses untuk aksi ini.');
        }

        return $next($request);
    }
}
