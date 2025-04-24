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

class TariffController extends Controller
{
    public function buy(Request $request): View
    {
        $tariff = PaymentTariff::where('id', $request->id)
            ->where('status', PaymentTariffStatusEnum::Active)
            ->firstOrFail();

        return view('tariff.buy', [
            'tariff' => $tariff,
        ]);
    }
}
