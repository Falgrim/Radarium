<?php

namespace App\Http\Controllers;

use App\Enum\PaymentStatusEnum;
use App\Enum\UserTariffStatusEnum;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Payment;
use App\Models\UserTariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class SubscribeController extends Controller
{
    public function main(Request $request): View
    {
        $subscribeActive = UserTariff::where('user_id', Auth::user()->id)
            ->where('status', UserTariffStatusEnum::Active)
            ->orderBy('date_end', 'DESC')
            ->get();

        $subscribeEnded = UserTariff::where('user_id', Auth::user()->id)
            ->where('status', UserTariffStatusEnum::Ended)
            ->orderBy('date_end', 'DESC')
            ->get();

        $payments = Payment::where('user_id', Auth::user()->id)
            ->with('paymentTariff')
            ->where('status', PaymentStatusEnum::New)
            ->orderBy('created_at', 'DESC')
            ->get();

        return view('profile.subscribe', [
            'subscribeActive' => $subscribeActive,
            'subscribeEnded' => $subscribeEnded,
            'payments' => $payments,
        ]);
    }
}
