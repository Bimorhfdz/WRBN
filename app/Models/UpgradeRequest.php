<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'consultation_id',
    'user_id',
    'current_service_id',
    'requested_service_id',
    'reason',
    'status',
])]
class UpgradeRequest extends Model
{
    use UsesRandomUuid;

    protected $table = 'upgrade_requests';
}
