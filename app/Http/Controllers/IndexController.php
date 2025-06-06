<?php

namespace App\Http\Controllers;

use App\Enum\ApiPostAiStatusEnum;
use App\Enum\PaymentTariffStatusEnum;
use App\Models\ApiPostUser;
use App\Models\PaymentTariff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class IndexController extends Controller
{
    public function index()
    {
        $contactSum = ApiPostUser::whereHas('specialists', function (Builder $query) {
            $query->where('status', '=', ApiPostAiStatusEnum::Active);
        });

        $contactSum = $contactSum->where(function (Builder $query) {
            $query->whereNotNull('phone')
                ->orWhere('username', '<>', '');
        });

        $contactSum = $contactSum->count();

        $contactTodaySum = ApiPostUser::whereHas('specialists', function (Builder $query) {
            $query->where('status', '=', ApiPostAiStatusEnum::Active);
            $query->where('created_at', '>=', Carbon::now()->startOfDay());
        });

        $contactTodaySum = $contactTodaySum->where(function (Builder $query) {
            $query->whereNotNull('phone')
                ->orWhere('username', '<>', '');
        });

        $contactTodaySum = $contactTodaySum->count();

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
