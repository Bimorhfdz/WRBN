<?php

namespace App\Services\Sheets;

use Illuminate\Support\Facades\Http;

class SheetsMirror
{
    /**
     * Fire-and-forget mirror of bookings into Google Sheets. Runs after the
     * response has been sent, swallows every error, and does nothing when the
     * webhook URL is empty.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function push(array $payload): void
    {
        $url = (string) config('wrbn.sheets_webhook_url');

        if ($url === '') {
            return;
        }

        dispatch(function () use ($url, $payload): void {
            try {
                Http::acceptJson()->post($url, $payload);
            } catch (\Throwable $exception) {
                report($exception);
            }
        })->afterResponse();
    }
}
