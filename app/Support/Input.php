<?php

namespace App\Support;

class Input
{
    /**
     * Trim and truncate a value exactly like cleanString() in the legacy Node backend.
     */
    public static function clean(mixed $value, int $max = 5000): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, $max);
    }

    public static function isEmail(?string $value): bool
    {
        return is_string($value) && preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $value) === 1;
    }

    public static function isValidDate(?string $value): bool
    {
        if (! $value) {
            return true;
        }

        return strtotime($value) !== false;
    }
}
