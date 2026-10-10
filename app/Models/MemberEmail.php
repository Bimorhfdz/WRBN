<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'plan_label', 'note', 'expires_at'])]
class MemberEmail extends Model
{
    use UsesRandomUuid;

    protected $table = 'member_emails';

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
