<?php

namespace App\Http\Controllers;

use App\Enum\PaymentTariffStatusEnum;
use App\Models\ApiPostUser;
use App\Models\PaymentTariff;
use Carbon\Carbon;

class IndexController extends Controller
{
    public function index()
    {
        $contactSum = ApiPostUser::count();
        $contactTodaySum = ApiPostUser::where('created_at', '>=', Carbon::now()->startOfDay())->count();

        $tariffs = PaymentTariff::where('status', PaymentTariffStatusEnum::Active)
            ->orderBy('period', 'ASC')
            ->get();

        return view('pages.index', [
            'contactSum' => $contactSum,
            'contactTodaySum' => $contactTodaySum,
            'tariffs' => $tariffs,
        ]);
    }
}
