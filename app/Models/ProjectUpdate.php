<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['consultation_id', 'title', 'message', 'status'])]
class ProjectUpdate extends Model
{
    use UsesRandomUuid;

    public const UPDATED_AT = null;

    protected $table = 'project_updates';
}
