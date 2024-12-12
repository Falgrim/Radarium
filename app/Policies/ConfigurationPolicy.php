<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use MoonShine\Models\MoonshineUser;
use App\Models\Configuration;
use MoonShine\Models\MoonshineUserRole;

class ConfigurationPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $user)
    {
        return true;
    }

    public function view(MoonshineUser $user, Configuration $model)
    {
        return true;
    }

    public function create(MoonshineUser $user)
    {
        return false;
    }

    public function update(MoonshineUser $user, Configuration $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function delete(MoonshineUser $user, Configuration $model)
    {
        return false;
    }

    public function massDelete(MoonshineUser $user)
    {
        return false;
    }

    public function restore(MoonshineUser $user, Configuration $model)
    {
        return false;
    }

    public function forceDelete(MoonshineUser $user, Configuration $model)
    {
        return false;
    }
}
