<?php

namespace App\Http\Controllers;

use App\Enum\ApiPostAiStatusEnum;
use App\Enum\PaymentTariffStatusEnum;
use App\Infrastructures\Facades\Repositories;
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
        })->count();

        $contactTodaySum = ApiPostUser::whereHas('specialists', function (Builder $query) {
            $query->where('status', '=', ApiPostAiStatusEnum::Active);
            $query->where('created_at', '>=', Carbon::now()->startOfDay());
        })->count();

        $tariffs = PaymentTariff::where('status', PaymentTariffStatusEnum::Active)
            ->orderBy('period', 'ASC')
            ->get();

        $userRoleList = Repositories::userRole()->getList();

        return view('pages.index', [
            'contactSum' => $contactSum,
            'contactTodaySum' => $contactTodaySum,
            'tariffs' => $tariffs,
            'userRoleList' => $userRoleList,
        ]);
    }
}
