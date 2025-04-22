<?php

namespace App\Http\Controllers;

use App\Enum\PaymentTariffStatusEnum;
use App\Models\PaymentTariff;

class IndexController extends Controller
{
    public function index()
    {
        $tariffs = PaymentTariff::where('status', PaymentTariffStatusEnum::Active)
            ->orderBy('period', 'ASC')
            ->get();

        return view('pages.index', [
            'tariffs' => $tariffs,
        ]);
    }
}
