<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Support\Input;
use App\Support\Rows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $reviews = DB::select('SELECT id, rating, name, role, text, created_at FROM reviews ORDER BY created_at DESC LIMIT 30');

        $reviews = array_map(function (object $review): array {
            $row = Rows::one($review);

            $joined = collect(preg_split('/\s+/', trim((string) $review->name)))
                ->filter()
                ->map(fn (string $part) => mb_substr($part, 0, 1))
                ->implode('');

            $row['initials'] = mb_strtoupper(mb_substr($joined, 0, 2));

            return $row;
        }, $reviews);

        return response()->json(['reviews' => $reviews]);
    }

    public function store(Request $request): JsonResponse
    {
        $rating = $request->input('rating');
        $name = Input::clean($request->input('name'), 60);
        $role = Input::clean($request->input('role'), 60) ?: 'Klien';
        $text = Input::clean($request->input('text'), 400);

        $validRating = is_numeric($rating) && (int) $rating == $rating && (int) $rating >= 1 && (int) $rating <= 5;

        if (! $validRating || mb_strlen($name) < 2 || mb_strlen($text) < 3) {
            return response()->json(['message' => 'Rating, nama, dan testimoni wajib valid.'], 400);
        }

        Review::query()->create([
            'user_id' => $request->user()?->id,
            'rating' => (int) $rating,
            'name' => $name,
            'role' => $role,
            'text' => $text,
        ]);

        return response()->json(['message' => 'Review berhasil dikirim.'], 201);
    }
}
