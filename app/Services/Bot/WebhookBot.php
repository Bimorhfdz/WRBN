<?php

namespace App\Services\Bot;

use Illuminate\Support\Facades\Http;

class WebhookBot implements BotService
{
    public function notify(string $event, array $payload): void
    {
        $url = (string) config('wrbn.bot.api_url');

        if ($url === '') {
            return;
        }

        $headers = [];
        $apiKey = (string) config('wrbn.bot.api_key');

        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $response = Http::withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->post($url, ['event' => $event, 'payload' => $payload]);

        if ($response->failed()) {
            logger()->warning('[bot.webhook] provider menolak notifikasi', [
                'event' => $event,
                'status' => $response->status(),
            ]);
        }
    }
}
