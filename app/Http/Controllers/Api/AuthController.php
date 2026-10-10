<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Input;
use App\Support\MemberPlan;
use App\Support\Rows;
use App\Support\UserShape;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $name = Input::clean($request->input('name'), 100);
        $email = strtolower(Input::clean($request->input('email'), 160));
        $password = (string) $request->input('password');

        if (mb_strlen($name) < 2) {
            return response()->json(['message' => 'Nama minimal 2 karakter.'], 400);
        }

        if (! Input::isEmail($email)) {
            return response()->json(['message' => 'Email tidak valid.'], 400);
        }

        if (mb_strlen($password) < 8) {
            return response()->json(['message' => 'Password minimal 8 karakter.'], 400);
        }

        if (User::query()->where('email', $email)->exists()) {
            return response()->json(['message' => 'Email sudah terdaftar.'], 409);
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => Hash::make($password, ['rounds' => 12]),
            'whatsapp' => null,
        ]);

        MemberPlan::sync($user);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => UserShape::public($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $email = strtolower(Input::clean($request->input('email'), 160));
        $password = (string) $request->input('password');

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        MemberPlan::sync($user);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => UserShape::public($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        return response()->json(['user' => UserShape::public($user)]);
    }

    /**
     * Shared helper for the client dashboard (req.user in the legacy code
     * carried both created_at and updated_at).
     *
     * @return array<string, mixed>|null
     */
    public static function currentUser(): ?array
    {
        $user = DB::table('users')
            ->select('id', 'name', 'email', 'whatsapp', 'created_at', 'updated_at')
            ->where('id', Auth::guard('web')->id())
            ->first();

        return $user ? Rows::one($user) : null;
    }
}
