<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['consultation_id', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'contents'])]
class ConsultationFile extends Model
{
    use UsesRandomUuid;

    public const UPDATED_AT = null;

    protected $table = 'consultation_files';

    protected $casts = [
        'size_bytes' => 'integer',
    ];
}
