<?php

namespace App\Policies;

use App\Models\PaymentTariff;
use App\Models\Review;
use Illuminate\Auth\Access\HandlesAuthorization;
use MoonShine\Models\MoonshineUser;
use MoonShine\Models\MoonshineUserRole;

class PaymentTariffPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $user)
    {
        return true;
    }

    public function view(MoonshineUser $user, PaymentTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return true;
    }

    public function create(MoonshineUser $user, PaymentTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function update(MoonshineUser $user, PaymentTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function delete(MoonshineUser $user, PaymentTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function massDelete(MoonshineUser $user)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function restore(MoonshineUser $user, PaymentTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function forceDelete(MoonshineUser $user, PaymentTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }
}
