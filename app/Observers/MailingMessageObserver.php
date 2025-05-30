<?php

namespace App\Observers;

use App\Models\MailingMessage;
use App\MoonShine\Resources\MailingMessageResource;
use MoonShine\Notifications\MoonShineNotification;

class MailingMessageObserver
{
    /**
     * Handle the MailingMessage "created" event.
     */
    public function created(MailingMessage $mailingMessage): void
    {

    }

    /**
     * Handle the MailingMessage "updated" event.
     */
    public function updated(MailingMessage $mailingMessage): void
    {

    }

    /**
     * Handle the MailingMessage "deleted" event.
     */
    public function deleted(MailingMessage $mailingMessage): void
    {

    }

    /**
     * Handle the MailingMessage "restored" event.
     */
    public function restored(MailingMessage $mailingMessage): void
    {

    }

    /**
     * Handle the MailingMessage "force deleted" event.
     */
    public function forceDeleted(MailingMessage $mailingMessage): void
    {

    }
}
