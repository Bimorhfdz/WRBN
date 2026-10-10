<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class IntakePayload
{
    /**
     * Biodata plus intake fields, merged into every Google Sheets mirror
     * payload so a row can be read on its own.
     *
     * @return array<string, mixed>
     */
    public static function for(string $userId, ?object $consultation = null): array
    {
        $profile = DB::table('user_profiles')->where('user_id', $userId)->first();
        $user = DB::table('users')->where('id', $userId)->first();

        return [
            'umur' => $profile->age ?? null,
            'institusi' => $profile->institution ?? null,
            'status' => $profile->occupation ?? null,
            'needType' => $consultation->need_type ?? null,
            'deadline' => $consultation->deadline ?? null,
            'projectCode' => $consultation->project_code ?? null,
            'accessPlan' => $user->access_plan ?? 'general',
        ];
    }
}
