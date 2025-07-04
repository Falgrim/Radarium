<?php

namespace App\Observers;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiPostUser;
use App\Models\Specialist;
use Illuminate\Support\Carbon;

class SpecialistObserver
{
    /**
     * Handle the UserTariff "created" event.
     */
    public function created(Specialist $specialist): void
    {
        if ($specialist->status == ApiPostAiStatusEnum::Active) {
            $apiPostUser = ApiPostUser::where('id', $specialist->api_post_user_id)
                ->where(function ($query) use ($specialist) {
                    $query->where('last_post_date', '<', $specialist->post_date)
                        ->orWhereNull('last_post_date');
                })
                ->first();

            if ($apiPostUser) {
                $apiPostUser->last_post_date = $specialist->post_date;
                $apiPostUser->save();
            }
        }
    }

    /**
     * Handle the UserTariff "updated" event.
     */
    public function updated(Specialist $specialist): void
    {
        if ($specialist->isDirty('status') AND $specialist->status == ApiPostAiStatusEnum::Active) {
            $apiPostUser = ApiPostUser::where('id', $specialist->api_post_user_id)
                ->where(function ($query) use ($specialist) {
                    $query->where('last_post_date', '<', $specialist->post_date)
                        ->orWhereNull('last_post_date');
                })
                ->first();

            if ($apiPostUser) {
                $apiPostUser->last_post_date = $specialist->post_date;
                $apiPostUser->save();
            }
        }
    }

    /**
     * Handle the UserTariff "deleted" event.
     */
    public function deleted(Specialist $specialist): void
    {
        //
    }

    /**
     * Handle the UserTariff "restored" event.
     */
    public function restored(Specialist $specialist): void
    {
        //
    }

    /**
     * Handle the UserTariff "force deleted" event.
     */
    public function forceDeleted(Specialist $specialist): void
    {
        //
    }
}
