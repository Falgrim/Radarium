<?php

namespace App\Observers;

use App\Models\ModerationAlert;
use App\MoonShine\Resources\ModerationAlertResource;
use MoonShine\Notifications\MoonShineNotification;

class ModerationAlertObserver
{
    /**
     * Handle the ModerationAlert "created" event.
     */
    public function created(ModerationAlert $moderationAlert): void
    {
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
