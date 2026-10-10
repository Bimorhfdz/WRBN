<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = DB::select(
            'SELECT id, slug, name, category, description, price, price_label AS priceLabel,
                    data_quota AS dataQuota, revision_quota AS revisionQuota, deliverable_label AS deliverableLabel
             FROM services WHERE active = 1 ORDER BY sort_order, created_at'
        );

        return response()->json(['services' => $services]);
    }
}
