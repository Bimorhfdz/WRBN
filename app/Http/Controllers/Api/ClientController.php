<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Support\Input;
use App\Support\Rows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        $paid = (int) DB::table('payments')->where('user_id', $userId)->where('status', 'paid')->count();
        $pending = (int) DB::table('payments')->where('user_id', $userId)->where('status', 'pending')->count();
        $consultations = (int) DB::table('consultations')->where('user_id', $userId)->count();

        $latest = DB::select(
            'SELECT c.id, c.title, c.status, c.updated_at, s.name service_name
             FROM consultations c JOIN services s ON s.id = c.service_id
             WHERE c.user_id = ? ORDER BY c.updated_at DESC LIMIT 5',
            [$userId]
        );

        $packages = DB::select(
            'SELECT s.id, s.slug, s.name, s.price_label AS priceLabel,
                    s.data_quota AS dataQuota, s.revision_quota AS revisionQuota, s.deliverable_label AS deliverableLabel,
                    (SELECT COUNT(*) FROM consultations c
                      WHERE c.user_id = ? AND c.service_id = s.id AND c.status != \'cancelled\') AS used
             FROM services s WHERE s.active = 1 ORDER BY s.sort_order',
            [$userId]
        );

        foreach ($packages as $package) {
            $package->remaining = max(0, (int) $package->dataQuota - (int) $package->used);
        }

        return response()->json([
            'user' => AuthController::currentUser(),
            'stats' => [
                'paidPayments' => $paid,
                'pendingPayments' => $pending,
                'consultations' => $consultations,
            ],
            'latestConsultations' => Rows::iso($latest),
            'packages' => $packages,
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['profile' => $this->profileShape((string) $request->user()->id)]);
    }

    /**
     * Upserts the biodata collected by layer 2 of the intake wizard. Every
     * column is optional; an empty string clears the value.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        $age = $request->input('age');

        if ($age !== null && $age !== '' && (! is_numeric($age) || (int) $age < 13 || (int) $age > 100)) {
            return response()->json(['message' => 'Umur harus antara 13 dan 100.'], 400);
        }

        $attributes = [
            'age' => ($age === null || $age === '') ? null : (int) $age,
            'occupation' => self::blankToNull(Input::clean($request->input('occupation'), 80)),
            'institution' => self::blankToNull(Input::clean($request->input('institution'), 160)),
            'level' => self::blankToNull(Input::clean($request->input('level'), 80)),
            'city' => self::blankToNull(Input::clean($request->input('city'), 80)),
            'referral' => self::blankToNull(Input::clean($request->input('referral'), 160)),
        ];

        $profile = UserProfile::query()->firstOrNew(['user_id' => $userId]);
        $profile->fill($attributes);
        $profile->save();

        return response()->json(['profile' => $this->profileShape($userId)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileShape(string $userId): array
    {
        $row = UserProfile::query()->where('user_id', $userId)->first();

        return [
            'age' => $row?->age,
            'occupation' => $row?->occupation,
            'institution' => $row?->institution,
            'level' => $row?->level,
            'city' => $row?->city,
            'referral' => $row?->referral,
        ];
    }

    private static function blankToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
