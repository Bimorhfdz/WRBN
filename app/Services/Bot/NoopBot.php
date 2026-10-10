<?php

namespace App\Services\Bot;

class NoopBot implements BotService
{
    public function notify(string $event, array $payload): void
    {
        //
    }
}
