<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'consultation_id',
    'user_id',
    'current_scheduled_at',
    'requested_scheduled_at',
    'reason',
    'status',
])]
class RescheduleRequest extends Model
{
    use UsesRandomUuid;

    protected $table = 'reschedule_requests';
}
