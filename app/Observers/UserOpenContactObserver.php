<?php

namespace App\Observers;

use App\Enum\UserTariffStatusEnum;
use App\Models\UserOpenContact;
use App\Models\UserTariff;
use Carbon\Carbon;

class UserOpenContactObserver
{
    /**
     * Handle the UserTariff "created" event.
     */
    public function created(UserOpenContact $userOpenContact): void
    {
        $userTariff = $userOpenContact->user->getFirstActiveTariff();

        if ($userTariff) {
            $userTariff->count_contacts_left = $userTariff->count_contacts_left - 1;
            $userTariff->save();
        }
    }

    /**
     * Handle the UserTariff "updated" event.
     */
    public function updated(UserOpenContact $userOpenContact): void
    {

    }

    /**
     * Handle the UserTariff "deleted" event.
     */
    public function deleted(UserOpenContact $userOpenContact): void
    {
        //
    }

    /**
     * Handle the UserTariff "restored" event.
     */
    public function restored(UserOpenContact $userOpenContact): void
    {
        //
    }

    /**
     * Handle the UserTariff "force deleted" event.
     */
    public function forceDeleted(UserOpenContact $userOpenContact): void
    {
        //
    }
}
