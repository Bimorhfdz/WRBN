<?php

namespace App\Support;

use App\Services\Bot\BotServiceFactory;
use Illuminate\Support\Facades\Log;

/**
 * Fire-and-forget bot notifications. Runs after the response is sent so a
 * slow or broken provider can never fail the request, and every error is
 * swallowed.
 */
class Bot
{
    public const PROJECT_CREATED = 'project.created';

    public const STAGE_CHANGED = 'project.stage_changed';

    public const PROGRESS_ADDED = 'project.progress';

    public const RESCHEDULE_RESOLVED = 'reschedule.resolved';

    public const UPGRADE_RESOLVED = 'upgrade.resolved';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function notify(string $event, array $payload): void
    {
        dispatch(function () use ($event, $payload): void {
            try {
                BotServiceFactory::make()->notify($event, $payload);
            } catch (\Throwable $exception) {
                Log::warning('[bot] notifikasi gagal dikirim', [
                    'event' => $event,
                    'error' => $exception->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
