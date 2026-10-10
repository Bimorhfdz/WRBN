<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $admin = Admin::query()->where('email', $email)->first();

        if (! $admin || ! Hash::check($password, $admin->password_hash)) {
            return response()->json(['message' => 'Email atau password admin salah.'], 401);
        }

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        session(['admin_login_at' => now()->getTimestamp()]);

        return response()->json([
            'message' => 'Login admin berhasil.',
            'admin' => $this->shape($admin),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('admin')->logout();
        session()->forget('admin_login_at');

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function session(): JsonResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return response()->json([
            'ok' => true,
            'role' => (string) ($admin->role ?? 'admin'),
            'admin' => $this->shape($admin),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shape(?Admin $admin): ?array
    {
        if (! $admin) {
            return null;
        }

        return [
            'id' => $admin->id,
            'email' => $admin->email,
            'name' => $admin->name,
            'role' => (string) ($admin->role ?? 'admin'),
        ];
    }
}
