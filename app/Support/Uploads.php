<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Uploads
{
    /**
     * storage_path rows written by the legacy Node backend are absolute, rows
     * written by Laravel live on the private "local" disk and are relative.
     */
    public static function absolutize(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path) === 1) {
            return $path;
        }

        return Storage::disk('local')->path($path);
    }

    public static function delete(?string $path): void
    {
        $absolute = self::absolutize($path);

        if ($absolute !== null && is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
