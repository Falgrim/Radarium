<?php

namespace App\Observers;

use App\Enum\UserTariffStatusEnum;
use App\Models\UserTariff;
use Carbon\Carbon;

class UserTariffObserver
{
    /**
     * Handle the UserTariff "created" event.
     */
    public function created(UserTariff $userTariff): void
    {
        //
    }

    /**
     * Handle the UserTariff "updated" event.
     */
    public function updated(UserTariff $userTariff): void
    {
        $haveChanges = false;
        if ($userTariff->count_contacts_left <= 0) {
            $userTariff->status = UserTariffStatusEnum::Ended;
            $haveChanges = true;
        }

        if ($userTariff->date_end <= Carbon::now()) {
            $userTariff->status = UserTariffStatusEnum::Ended;
            $haveChanges = true;
        }

        if ($haveChanges) {
            $userTariff->saveQuietly();
        }
    }

    /**
     * Handle the UserTariff "deleted" event.
     */
    public function deleted(UserTariff $userTariff): void
    {
        //
    }

    /**
     * Handle the UserTariff "restored" event.
     */
    public function restored(UserTariff $userTariff): void
    {
        //
    }

    /**
     * Handle the UserTariff "force deleted" event.
     */
    public function forceDeleted(UserTariff $userTariff): void
    {
        //
    }
}
