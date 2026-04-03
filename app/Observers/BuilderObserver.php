<?php

namespace App\Observers;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiPostUser;
use App\Models\Builder;
use App\Models\Specialist;
use Illuminate\Support\Carbon;

class BuilderObserver
{
    /**
     * Handle the UserTariff "created" event.
     */
    public function created(Builder $builder): void
    {
        if ($builder->status == ApiPostAiStatusEnum::Active) {
            $apiPostUser = ApiPostUser::where('id', $builder->api_post_user_id)
                ->where(function ($query) use ($builder) {
                    $query->where('last_post_date', '<', $builder->post_date)
                        ->orWhereNull('last_post_date');
                })
                ->first();

            if ($apiPostUser) {
                $apiPostUser->last_post_date = $builder->post_date;
                $apiPostUser->save();
            }
        }
    }

    /**
     * Handle the UserTariff "updated" event.
     */
    public function updated(Builder $builder): void
    {
        if ($builder->isDirty('status') AND $builder->status == ApiPostAiStatusEnum::Active) {
            $apiPostUser = ApiPostUser::where('id', $builder->api_post_user_id)
                ->where(function ($query) use ($builder) {
                    $query->where('last_post_date', '<', $builder->post_date)
                        ->orWhereNull('last_post_date');
                })
                ->first();

            if ($apiPostUser) {
                $apiPostUser->last_post_date = $builder->post_date;
                $apiPostUser->save();
            }
        }
    }

    /**
     * Handle the UserTariff "deleted" event.
     */
    public function deleted(Builder $builder): void
    {
        //
    }

    /**
     * Handle the UserTariff "restored" event.
     */
    public function restored(Builder $builder): void
    {
        //
    }

    /**
     * Handle the UserTariff "force deleted" event.
     */
    public function forceDeleted(Builder $builder): void
    {
        //
    }
}
