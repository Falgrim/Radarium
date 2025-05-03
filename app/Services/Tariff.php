<?php

namespace App\Services;

use App\Models\ApiPostUser;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserOpenContact;
use App\Models\UserTariff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Tariff
{
    protected ?User $user;

    public function __construct() {
        $this->user = Auth::check() ? Auth::user() : null;
    }

    public function checkContactAccess(?ApiPostUser $postUser = null): bool
    {
        if (is_null($this->user)) {
            return false;
        }

        if ($this->checkOpenContact($postUser) OR $this->user->getLeftContacts()['count_contacts_left'] > 0) {
            return true;
        }

        if ($this->user->free_contacts) {
            return true;
        }

        return false;
    }

    public function checkOpenContact(?ApiPostUser $postUser = null): bool
    {
        if (is_null($this->user)) {
            return false;
        }

        if (!is_null($postUser) AND $this->getOpenContactLog($this->user, $postUser)) {
            return true;
        }

        return false;
    }

    public function getOpenContactLog(User $user, ApiPostUser $postUser): ?UserOpenContact
    {
        return UserOpenContact::where('user_id', $user->id)->where('api_post_user_id', $postUser->id)->first();
    }

    public function addOpenContactLog(?User $user, ApiPostUser $postUser)
    {
        if (is_null($user)) {
            return false;
        }

        $userTariff = $user->getFirstActiveTariff();

        if (is_null($userTariff)) {
            return $this->freeContactsMinus($postUser);
        }

        UserOpenContact::firstOrCreate([
            'user_id' => $user->id,
            'api_post_user_id' => $postUser->id,
            'user_tariff_id' => $userTariff->id,
        ]);
    }

    protected function freeContactsMinus(ApiPostUser $postUser)
    {
        if (!$this->user->free_contacts) {
            return false;
        }

        $this->user->free_contacts = $this->user->free_contacts-1;
        $this->user->save();

        UserOpenContact::firstOrCreate([
            'user_id' => $this->user->id,
            'api_post_user_id' => $postUser->id,
            'user_tariff_id' => 0,
        ]);

        return true;
    }

    public static function getInvoiceID(Payment $payment): string
    {
        return $payment->id.'0'.$payment->user_id;
    }
}
