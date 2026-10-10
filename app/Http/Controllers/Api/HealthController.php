<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            return response()->json(['ok' => true, 'service' => 'wrbn-backend', 'database' => 'ok']);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['ok' => false, 'service' => 'wrbn-backend', 'database' => 'error'], 503);
        }
    }
}
