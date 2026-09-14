<?php

namespace Tests\Unit\Services;

use App\Models\ApiPostUser;
use App\Models\User;
use App\Services\Tariff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TariffTrialAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'user_role_id' => 1,
            'free_contacts' => 3,
        ], $attributes));
    }

    protected function makePostUser(): ApiPostUser
    {
        return ApiPostUser::create([
            'user_id' => 'tg-'.uniqid(),
            'channel_source' => 'telegram',
        ]);
    }

    public function test_new_free_contact_allowed_within_trial_window(): void
    {
        $user = $this->makeUser(['first_login_at' => now()->subDay()]);
        Auth::login($user);

        $tariff = new Tariff();
        $postUser = $this->makePostUser();

        $this->assertTrue($tariff->checkContactAccess($postUser));
        $this->assertTrue($tariff->checkAccessToOpen());
    }

    public function test_new_free_contact_denied_when_trial_expired_even_with_contacts_left(): void
    {
        $user = $this->makeUser(['first_login_at' => now()->subDays(4)]);
        Auth::login($user);

        $tariff = new Tariff();
        $postUser = $this->makePostUser();

        $this->assertFalse($tariff->checkAccessToOpen());
        $this->assertFalse($tariff->checkContactAccess($postUser));
    }

    public function test_already_opened_contact_still_accessible_after_trial_expired(): void
    {
        $user = $this->makeUser(['first_login_at' => now()->subDay()]);
        Auth::login($user);

        $tariff = new Tariff();
        $postUser = $this->makePostUser();

        // Открываем контакт, пока тест-драйв ещё активен.
        $this->assertTrue($tariff->checkOpenContact($postUser));
        $tariff->addOpenContactLog($user, $postUser);

        // Триал истёк — но уже открытый контакт остаётся доступен.
        $user->forceFill(['first_login_at' => now()->subDays(10)])->save();
        $tariffAfterExpiry = new Tariff();

        $this->assertTrue($tariffAfterExpiry->checkOpenContact($postUser));
        $this->assertTrue($tariffAfterExpiry->checkContactAccess($postUser));
    }
}
