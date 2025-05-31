<?php

namespace App\Policies;

use App\Models\MailingMessageLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use MoonShine\Models\MoonshineUser;
use MoonShine\Models\MoonshineUserRole;

class MailingMessageLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $user)
    {
        return true;
    }

    public function view(MoonshineUser $user, MailingMessageLog $model)
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

    public function update(MoonshineUser $user, MailingMessageLog $model)
    {
        return false;
    }

    public function delete(MoonshineUser $user, MailingMessageLog $model)
    {
        return false;
    }

    public function massDelete(MoonshineUser $user)
    {
        return false;
    }

    public function restore(MoonshineUser $user, MailingMessageLog $model)
    {
        return false;
    }

    public function forceDelete(MoonshineUser $user, MailingMessageLog $model)
    {
        return false;
    }
}
