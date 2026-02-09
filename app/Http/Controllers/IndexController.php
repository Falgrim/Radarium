<?php

namespace App\Http\Controllers;

use App\Enum\ApiPostAiStatusEnum;
use App\Enum\PaymentTariffStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiPostUser;
use App\Models\PaymentTariff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

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

        $builderContactSum = ApiPostUser::whereHas('builders', function (Builder $query) {
            $query->where('status', '=', ApiPostAiStatusEnum::Active);
        })
            ->where(function (Builder $query) {
                $query->whereNotNull('phone')
                    ->orWhere('username', '<>', '');
            })
            ->count();

        // Кэшируем вычисление contactTodaySum на 1 час
        $contactTodaySum = Cache::remember('contact_today_sum', 3600, function () {
            $contactTodaySum = ApiPostUser::whereHas('specialists', function (Builder $query) {
                $query->where('status', '=', ApiPostAiStatusEnum::Active);
                $query->where('created_at', '>=', Carbon::now()->startOfDay());
            });

            $contactTodaySum = $contactTodaySum->where(function (Builder $query) {
                $query->whereNotNull('phone')
                    ->orWhere('username', '<>', '');
            });

            $contactTodaySum = $contactTodaySum->count();

            if ($contactTodaySum < 50) {
                $contactTodaySum += rand(50,59);
            }
            return $contactTodaySum;
        });

        $builderContactTodaySum = Cache::remember('builder_contact_today_sum', 3600, function () {
            $sum = ApiPostUser::whereHas('builders', function (Builder $query) {
                $query->where('status', '=', ApiPostAiStatusEnum::Active);
                $query->where('created_at', '>=', Carbon::now()->startOfDay());
            })
                ->where(function (Builder $query) {
                    $query->whereNotNull('phone')
                        ->orWhere('username', '<>', '');
                })
                ->count();
            if ($sum < 50) {
                $sum += rand(50, 59);
            }
            return $sum;
        });

        $tariffs = PaymentTariff::where('status', PaymentTariffStatusEnum::Active)
            ->orderBy('period', 'ASC')
            ->get();

        $userRoleList = Repositories::userRole()->getList();

        return view('pages.index', [
            'contactSum' => $contactSum,
            'contactTodaySum' => $contactTodaySum,
            'builderContactSum' => $builderContactSum,
            'builderContactTodaySum' => $builderContactTodaySum,
            'tariffs' => $tariffs,
            'userRoleList' => $userRoleList,
        ]);
    }
    
    public function tech()
    {
        return view('pages.tech');
    }
}
