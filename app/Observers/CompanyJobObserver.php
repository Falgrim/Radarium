<?php

namespace App\Observers;

use App\Enum\ApiPostUserMailingStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiPostUser;
use App\Models\CompanyJob;

class CompanyJobObserver
{
    /**
     * Handle the UserTariff "created" event.
     */
    public function created(CompanyJob $companyJob): void
    {
        if ($companyJob->status == CompanyJobStatusEnum::Active) {
            $apiPostUser = ApiPostUser::where('id', $companyJob->api_post_user_id)
                ->where(function ($query) use ($companyJob) {
                    $query->where('last_post_date', '<', $companyJob->post_date)
                        ->orWhereNull('last_post_date');
                })
                ->first();

            if ($apiPostUser) {
                if ($apiPostUser->send_welcome_msg == ApiPostUserMailingStatusEnum::Waiting) {
                    $mailingTgNewCompany = Repositories::setting()->findByName('mailing_tg_new_company');
                    $sendWelcomeMsg = ApiPostUserMailingStatusEnum::Disabled;

                    if ($mailingTgNewCompany?->value) {
                        $sendWelcomeMsg = ApiPostUserMailingStatusEnum::ToSend;
                    }

                    $apiPostUser->send_welcome_msg = $sendWelcomeMsg;
                }

                $apiPostUser->last_post_date = $companyJob->post_date;
                $apiPostUser->is_company = 1;
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
    public function forceDeleted(CompanyJob $companyJob): void
    {
        //
    }
}
