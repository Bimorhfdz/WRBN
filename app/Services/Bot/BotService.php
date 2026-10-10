<?php

namespace App\Services\Bot;

/**
 * One notifier per provider, picked by config('wrbn.bot.provider') exactly
 * like the payment gateway. Implementations must never throw: a broken
 * provider must not fail the request that triggered the notification.
 */
interface BotService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function notify(string $event, array $payload): void;
}
