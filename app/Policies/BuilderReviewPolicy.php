<?php

namespace App\Policies;

use App\Models\BuilderReview;
use Illuminate\Auth\Access\HandlesAuthorization;
use MoonShine\Models\MoonshineUser;
use MoonShine\Models\MoonshineUserRole;

class BuilderReviewPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $user)
    {
        return true;
    }

    public function view(MoonshineUser $user, BuilderReview$model)
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

    public function update(MoonshineUser $user, BuilderReview $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function delete(MoonshineUser $user, BuilderReview $model)
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

    public function restore(MoonshineUser $user, BuilderReview $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }

    public function forceDelete(MoonshineUser $user, BuilderReview $model)
    {
        if ($user->isSuperUser()) {
            return true;
        }

        return false;
    }
}
