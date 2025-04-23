<?php

namespace App\Policies;

use App\Models\UserTariff;
use Illuminate\Auth\Access\HandlesAuthorization;
use MoonShine\Models\MoonshineUser;

class UserTariffPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $user)
    {
        return true;
    }

    public function view(MoonshineUser $user, UserTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return true;
    }

    public function create(MoonshineUser $user, UserTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function update(MoonshineUser $user, UserTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function delete(MoonshineUser $user, UserTariff $model)
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

    public function restore(MoonshineUser $user, UserTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function forceDelete(MoonshineUser $user, UserTariff $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }
}
