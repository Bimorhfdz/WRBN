<?php

namespace App\Services\Bot;

/**
 * Default provider: no external API. The message is logged as a wa.me deep
 * link so an operator can send it by hand. Swap to WebhookBot once a real
 * WhatsApp provider is wired up.
 */
class LinkBot implements BotService
{
    public function notify(string $event, array $payload): void
    {
        $recipient = trim((string) ($payload['whatsapp'] ?? ''));

        if ($recipient === '') {
            $recipient = (string) config('wrbn.cp_general_whatsapp');
        }

        $text = trim((string) ($payload['text'] ?? ''));

        if ($text === '') {
            $text = sprintf('[WRBN] %s - project %s', $event, (string) ($payload['projectCode'] ?? '-'));
        }

        $link = $recipient === ''
            ? 'https://wa.me/?text='.rawurlencode($text)
            : 'https://wa.me/'.preg_replace('/\D+/', '', $recipient).'?text='.rawurlencode($text);

        logger()->info('[bot.link] '.$event, [
            'event' => $event,
            'link' => $link,
            'payload' => $payload,
        ]);
    }
}
