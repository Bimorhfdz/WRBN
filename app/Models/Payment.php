<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'service_id',
    'amount',
    'currency',
    'status',
    'provider',
    'provider_reference',
    'scheduled_at',
    'whatsapp',
    'notes',
    'paid_at',
])]
class Payment extends Model
{
    use UsesRandomUuid;

    protected $table = 'payments';

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
