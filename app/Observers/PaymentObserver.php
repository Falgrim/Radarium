<?php

namespace App\Observers;

use App\Enum\PaymentStatusEnum;
use App\Enum\UserTariffStatusEnum;
use App\Models\Payment;
use App\Models\UserTariff;
use Carbon\Carbon;

class PaymentObserver
{
    /**
     * Handle the UserTariff "created" event.
     */
    public function created(Payment $payment): void
    {
        if ($payment->status === PaymentStatusEnum::Success) {
            $this->createUserTariff($payment);
        }
    }

    /**
     * Handle the UserTariff "updated" event.
     */
    public function updated(Payment $payment): void
    {
        if ($payment->status === PaymentStatusEnum::Success) {
            $this->createUserTariff($payment);
        }
    }

    /**
     * Handle the UserTariff "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        //
    }

    /**
     * Handle the UserTariff "restored" event.
     */
    public function restored(UserTariff $payment): void
    {
        //
    }

    /**
     * Handle the UserTariff "force deleted" event.
     */
    public function forceDeleted(Payment $payment): void
    {
        //
    }

    protected function createUserTariff(Payment $payment)
    {
        if (!$payment->payment_tariff_id) {
            return false;
        }

        UserTariff::firstOrcreate([
            'user_id' => $payment->user_id,
            'payment_tariff_id' => $payment->payment_tariff_id,
            'payment_id' => $payment->id,
        ],
        [
            'user_id' => $payment->user_id,
            'payment_tariff_id' => $payment->payment_tariff_id,
            'api_data_type' => $payment->paymentTariff->api_data_type,
            'period' => $payment->paymentTariff->period,
            'count_contacts' => $payment->paymentTariff->count_contacts,
            'count_contacts_left' => $payment->paymentTariff->count_contacts,
            'status' => UserTariffStatusEnum::Active,
            'date_start' => Carbon::now(),
            'date_end' => Carbon::now()->addDays($payment->paymentTariff->period),
        ]);
    }
}
