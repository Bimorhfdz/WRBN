<?php

namespace App\Support;

use App\Models\User;

class UserShape
{
    /**
     * publicUser() from the legacy auth controller.
     *
     * @return array<string, mixed>|null
     */
    public static function public(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp' => $user->whatsapp,
            'access_plan' => $user->access_plan ?? 'general',
            'created_at' => $user->created_at ? Rows::toIso($user->created_at) : null,
        ];
    }

    /**
     * req.user as loaded by the legacy requireAuth middleware.
     *
     * @return array<string, mixed>|null
     */
    public static function session(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp' => $user->whatsapp,
            'access_plan' => $user->access_plan ?? 'general',
            'created_at' => $user->created_at ? Rows::toIso($user->created_at) : null,
            'updated_at' => $user->updated_at ? Rows::toIso($user->updated_at) : null,
        ];
    }
}
