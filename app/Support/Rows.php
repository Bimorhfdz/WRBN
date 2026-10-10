<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * The legacy Node backend stored ISO-8601 UTC strings in SQLite. MySQL returns
 * "Y-m-d H:i:s", so every read is normalised back to the exact shape the old
 * frontend expects.
 */
class Rows
{
    private const DATE_KEYS = ['created_at', 'updated_at', 'paid_at'];

    /**
     * @param  iterable<int, object|array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function iso(iterable $rows): array
    {
        $rows = is_array($rows) ? $rows : $rows->all();

        return array_map(fn ($row) => self::one($row), array_values($rows));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function one(mixed $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $row = (array) $row;

        foreach (self::DATE_KEYS as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                $row[$key] = self::toIso($row[$key]);
            }
        }

        return $row;
    }

    public static function toIso(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value)->utc()->format('Y-m-d\TH:i:s.v\Z');
        }

        return Carbon::parse((string) $value)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
