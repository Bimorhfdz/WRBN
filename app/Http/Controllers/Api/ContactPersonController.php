<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactPerson;
use Illuminate\Http\JsonResponse;

class ContactPersonController extends Controller
{
    /**
     * Public endpoint: the landing page swaps its placeholder WhatsApp number
     * for this. Falls back to any active person, then to an empty shape.
     */
    public function show(): JsonResponse
    {
        $person = ContactPerson::query()
            ->where('active', true)
            ->orderByDesc('is_general')
            ->orderBy('created_at')
            ->first();

        if (! $person) {
            return response()->json(['contactPerson' => null]);
        }

        return response()->json(['contactPerson' => $person->toPublicArray()]);
    }
}
