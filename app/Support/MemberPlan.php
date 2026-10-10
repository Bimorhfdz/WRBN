<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The whitelist behind door number two. A user only keeps the member door
 * while their address is listed and not expired; removing the row never
 * touches the account or its projects, it just flips the next login back to
 * the general plan.
 */
class MemberPlan
{
    public const GENERAL = 'general';

    public const MEMBER = 'member';

    public static function isListed(string $email): bool
    {
        return DB::table('member_emails')
            ->where('email', strtolower(trim($email)))
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public static function planFor(string $email): string
    {
        return self::isListed($email) ? self::MEMBER : self::GENERAL;
    }

    public static function sync(User $user): User
    {
        $plan = self::planFor($user->email);

        if ($user->access_plan !== $plan) {
            $user->access_plan = $plan;
            $user->save();
        }

        return $user;
    }
}
