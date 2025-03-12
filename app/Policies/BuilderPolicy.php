<?php

namespace App\Policies;

use App\Models\ApiPostUser;
use App\Models\Builder;
use Illuminate\Auth\Access\HandlesAuthorization;
use MoonShine\Models\MoonshineUser;
use MoonShine\Models\MoonshineUserRole;

class BuilderPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $user)
    {
        return true;
    }

    public function view(MoonshineUser $user, Builder $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return true;
    }

    public function create(MoonshineUser $user)
    {
        return false;
    }

    public function update(MoonshineUser $user, Builder $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function delete(MoonshineUser $user, Builder $model)
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

    public function restore(MoonshineUser $user, Builder $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function forceDelete(MoonshineUser $user, Builder $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }
}
