<?php

namespace App\Http\Controllers;

use App\Enum\PaymentServicePayEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTariffStatusEnum;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Payment;
use App\Models\PaymentTariff;
use App\Models\UserTariff;
use App\Services\Tariff;
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

        $robokassaConf = config('payment.robokassa');

        $conf = [
            'login' => $robokassaConf['login'],
            'password1' => $robokassaConf['pass1'],
            'password2' => $robokassaConf['pass1'],
            'hashType' => 'md5',
        ];

        if ($robokassaConf['is_test']) {
            $conf['is_test'] = $robokassaConf['is_test'];
            $conf['test_password1'] = $robokassaConf['pass1'];
            $conf['test_password2'] = $robokassaConf['pass2'];
        }

        $robokassa = new Robokassa($conf);

        $payment = Payment::firstOrCreate([
            'user_id' => Auth::user()->id,
            'payment_tariff_id' => $paymentTariff->id,
            'payment_service' => PaymentServicePayEnum::Robokassa,
            'status' => PaymentStatusEnum::New,
            'sum' => $paymentTariff->price,
        ],
        [
            'user_id' => Auth::user()->id,
            'payment_tariff_id' => $paymentTariff->id,
            'payment_service' => PaymentServicePayEnum::Robokassa,
            'status' => PaymentStatusEnum::New,
            'sum' => $paymentTariff->price,
        ]);

        $params = [
            'OutSum' => $paymentTariff->price,
            'InvoiceID' => Tariff::getInvoiceID($payment),
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

        $url = $robokassa->sendPaymentRequestCurl($params);

        list($params, $hash) = explode('Merchant/Index/', $url);

        if (!empty($hash) AND (!$payment->payment_hash OR $payment->payment_hash != $hash)) {
            $payment->payment_hash = $hash;
            $payment->save();
        }

        return $url;
    }
}
