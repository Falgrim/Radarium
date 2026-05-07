<?php

namespace App\Observers;

use App\Models\ModerationAlert;
use App\MoonShine\Resources\ModerationAlertResource;
use Illuminate\Support\Facades\Log;
use MoonShine\Notifications\MoonShineNotification;
use Throwable;

class ModerationAlertObserver
{
    /**
     * Handle the ModerationAlert "created" event.
     */
    public function created(ModerationAlert $moderationAlert): void
    {
        try {
            $page = (new ModerationAlertResource())->detailPageUrl($moderationAlert->id);

            MoonShineNotification::send(
                message: 'Новая модерация',
                // Необязательная кнопка
                button: [
                    'link' => $page,
                    'label' => 'Открыть'
                ],
                // Необязательный цвет иконки (purple, pink, blue, green, yellow, red, gray)
                color: 'yellow'
            );
        } catch (Throwable $e) {
            Log::warning('ModerationAlert: уведомление MoonShine не отправлено', [
                'moderation_alert_id' => $moderationAlert->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the ModerationAlert "updated" event.
     */
    public function updated(ModerationAlert $moderationAlert): void
    {
        //
    }

    /**
     * Handle the ModerationAlert "deleted" event.
     */
    public function deleted(ModerationAlert $moderationAlert): void
    {
        //
    }

    /**
     * Handle the ModerationAlert "restored" event.
     */
    public function restored(ModerationAlert $moderationAlert): void
    {
        //
    }

    /**
     * Handle the ModerationAlert "force deleted" event.
     */
    public function forceDeleted(ModerationAlert $moderationAlert): void
    {
        //
    }
}
