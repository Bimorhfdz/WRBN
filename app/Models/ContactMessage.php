<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'need', 'message'])]
class ContactMessage extends Model
{
    use UsesRandomUuid;

    public const UPDATED_AT = null;

    protected $table = 'contact_messages';
}
