<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultationFile;
use App\Support\Rows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileController extends Controller
{
    /**
     * Extension/MIME whitelist copied from legacy-node/backend/middleware/upload.js.
     */
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'csv' => ['text/csv', 'application/csv', 'text/plain'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'xls' => ['application/vnd.ms-excel'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'txt' => ['text/plain'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    private function owned(Request $request, string $id): ?object
    {
        return DB::selectOne('SELECT id FROM consultations WHERE id = ? AND user_id = ?', [$id, $request->user()->id]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        if (! $this->owned($request, $id)) {
            return response()->json(['message' => 'Konsultasi tidak ditemukan.'], 404);
        }

        if (! $request->hasFile('file')) {
            return response()->json(['message' => 'File wajib dipilih.'], 400);
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeTypes = self::ALLOWED[$extension] ?? null;
        $clientMime = (string) $file->getClientMimeType();

        if ($mimeTypes === null || ! in_array($clientMime, $mimeTypes, true)) {
            return response()->json(['message' => 'Tipe file tidak diizinkan.'], 400);
        }

        $maxBytes = (int) config('wrbn.max_file_size_mb') * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            return response()->json(['message' => 'Ukuran file terlalu besar.'], 413);
        }

        $row = ConsultationFile::query()->create([
            'consultation_id' => $id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => null,
            'mime_type' => $clientMime,
            'size_bytes' => (int) $file->getSize(),
            'contents' => file_get_contents($file->getRealPath()) ?: null,
        ]);

        return response()->json([
            'file' => [
                'id' => $row->id,
                'originalName' => $row->original_name,
                'mimeType' => $row->mime_type,
                'sizeBytes' => $row->size_bytes,
                'createdAt' => Rows::toIso($row->created_at),
            ],
        ], 201);
    }

    public function index(Request $request, string $id): JsonResponse
    {
        if (! $this->owned($request, $id)) {
            return response()->json(['message' => 'Konsultasi tidak ditemukan.'], 404);
        }

        $files = DB::select(
            'SELECT id, original_name, mime_type, size_bytes, created_at
             FROM consultation_files WHERE consultation_id = ? ORDER BY created_at DESC',
            [$id]
        );

        return response()->json(['files' => Rows::iso($files)]);
    }
}
