<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Behaves like Laravel's HasUuids but generates random v4 UUIDs
 * (crypto.randomUUID() in the legacy Node backend) instead of time-ordered
 * v7 UUIDs. The project code uses the first 6 characters of the UUID, so an
 * ordered UUID would collide between projects created close together.
 */
trait UsesRandomUuid
{
    use HasUuids;

    public function newUniqueId()
    {
        return (string) Str::uuid();
    }
}
