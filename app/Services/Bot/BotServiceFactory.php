<?php

namespace App\Services\Bot;

class BotServiceFactory
{
    public static function make(): BotService
    {
        return match (strtolower((string) config('wrbn.bot.provider'))) {
            'webhook' => new WebhookBot,
            'none' => new NoopBot,
            default => new LinkBot,
        };
    }
}
