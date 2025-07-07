<?php

namespace App\Observers;

use App\Enum\ApiPostAiStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Models\ApiPostUser;
use App\Models\CompanyJob;
use App\Models\Specialist;
use Illuminate\Support\Carbon;

class CompanyJobObserver
{
    /**
     * Handle the UserTariff "created" event.
     */
    public function created(CompanyJob $companyJob): void
    {
        if ($companyJob->status == ApiPostAiStatusEnum::Active) {
            $apiPostUser = ApiPostUser::where('id', $companyJob->api_post_user_id)
                ->where(function ($query) use ($companyJob) {
                    $query->where('last_post_date', '<', $companyJob->post_date)
                        ->orWhereNull('last_post_date');
                })
                ->first();

            if ($apiPostUser) {
                $apiPostUser->last_post_date = $companyJob->post_date;
                $apiPostUser->save();
            }
        }
    }

    /**
     * Handle the UserTariff "updated" event.
     */
    public function updated(CompanyJob $companyJob): void
    {
        if ($companyJob->isDirty('status') AND $companyJob->status == CompanyJobStatusEnum::Active) {
            $apiPostUser = ApiPostUser::where('id', $companyJob->api_post_user_id)
                ->where(function ($query) use ($companyJob) {
                    $query->where('last_post_date', '<', $companyJob->post_date)
                        ->orWhereNull('last_post_date');
                })
                ->first();

            if ($apiPostUser) {
                $apiPostUser->last_post_date = $companyJob->post_date;
                $apiPostUser->save();
            }
        }
    }

    /**
     * Handle the UserTariff "deleted" event.
     */
    public function deleted(CompanyJob $companyJob): void
    {
        //
    }

    /**
     * Handle the UserTariff "restored" event.
     */
    public function restored(CompanyJob $companyJob): void
    {
        //
    }

    /**
     * Handle the UserTariff "force deleted" event.
     */
    public function forceDeleted(Specialist $companyJob): void
    {
        //
    }
}
