<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $name = Input::clean($request->input('name'), 100);
        $email = strtolower(Input::clean($request->input('email'), 160));
        $need = Input::clean($request->input('need'), 160);
        $message = Input::clean($request->input('message'), 5000);

        if (mb_strlen($name) < 2 || ! Input::isEmail($email) || $need === '' || mb_strlen($message) < 5) {
            return response()->json(['message' => 'Data kontak belum lengkap atau tidak valid.'], 400);
        }

        ContactMessage::query()->create([
            'name' => $name,
            'email' => $email,
            'need' => $need,
            'message' => $message,
        ]);

        return response()->json(['message' => 'Brief berhasil diterima.'], 201);
    }
}
