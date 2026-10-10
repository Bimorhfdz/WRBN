<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'category', 'description', 'price', 'price_label', 'active', 'sort_order'])]
class Service extends Model
{
    use UsesRandomUuid;

    protected $table = 'services';

    protected $casts = [
        'price' => 'integer',
        'active' => 'boolean',
    ];
}
