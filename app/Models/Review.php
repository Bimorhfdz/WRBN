<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'rating', 'name', 'role', 'text'])]
class Review extends Model
{
    use UsesRandomUuid;

    public const UPDATED_AT = null;

    protected $table = 'reviews';

    protected $casts = [
        'rating' => 'integer',
    ];
}
