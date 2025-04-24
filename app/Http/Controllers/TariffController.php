<?php

namespace App\Http\Controllers;

use App\Enum\PaymentTariffStatusEnum;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\PaymentTariff;
use App\Models\UserTariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Robokassa\Robokassa;

class TariffController extends Controller
{
    public function buy(Request $request): View
    {
        $paymentTariff = PaymentTariff::where('id', $request->id)
            ->where('status', PaymentTariffStatusEnum::Active)
            ->firstOrFail();

        return view('tariff.buy', [
            'tariff' => $paymentTariff,
            'paymentLink' => $this->getPaymentLink($paymentTariff),
        ]);
    }

    private function getPaymentLink(PaymentTariff $paymentTariff)
    {
        if (!Auth::user()) {
            return false;
        }

        $robokassa = new Robokassa([
            'login' => 'merchant_login',
            'password1' => 'password1',
            'password2' => 'password2',
            'hashType' => 'md5'
        ]);

        $params = [
            'OutSum' => $paymentTariff->price,
            'InvoiceID' => 88512512,
            'Description' => 'Покупка тарифа "'.$paymentTariff->title.'"',
            'Receipt' => [
                'items' => [
                    [
                        'name' => 'Покупка тарифа "'.$paymentTariff->title.'"',
                        'quantity' => 1,
                        'sum' => $paymentTariff->price,
                        'payment_method' => 'full_payment',
                        'payment_object' => 'commodity',
                        'tax' => 'none'
                    ]
                ]
            ]
        ];

        return $robokassa->sendPaymentRequestCurl($params);
    }
}
