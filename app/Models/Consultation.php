<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_code',
    'user_id',
    'service_id',
    'payment_id',
    'title',
    'description',
    'scheduled_at',
    'meeting_url',
    'consultant_name',
    'contact_person',
    'notes',
    'status',
    'workflow_stage',
    'need_type',
    'deadline',
])]
class Consultation extends Model
{
    use UsesRandomUuid;

    protected $table = 'consultations';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class, 'consultation_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ConsultationFile::class, 'consultation_id');
    }

    public function rescheduleRequests(): HasMany
    {
        return $this->hasMany(RescheduleRequest::class, 'consultation_id');
    }

    public function upgradeRequests(): HasMany
    {
        return $this->hasMany(UpgradeRequest::class, 'consultation_id');
    }
}
