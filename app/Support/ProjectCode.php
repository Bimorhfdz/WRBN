<?php

namespace App\Support;

use Carbon\Carbon;

class ProjectCode
{
    /**
     * WRBN-YYYYMMDD-XXXXXX — date from created_at in UTC plus the first six
     * characters of the UUID primary key, uppercased.
     */
    public static function make(string $createdAt, string $projectId): string
    {
        $date = Carbon::parse($createdAt)->utc()->format('Ymd');
        $suffix = strtoupper(substr(str_replace('-', '', $projectId), 0, 6));

        return sprintf('WRBN-%s-%s', $date, $suffix);
    }
}
