<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['email', 'password_hash', 'name', 'role'])]
#[Hidden(['password_hash'])]
class Admin extends Authenticatable
{
    use UsesRandomUuid;

    protected $table = 'admins';

    /**
     * Older rows and freshly built instances default to the plain admin role.
     */
    protected $attributes = ['role' => 'admin'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The column holding the password hash in the legacy schema.
     */
    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }
}
