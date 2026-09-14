<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('catalog.builders', absolute: false));
    }

    public function test_last_login_at_is_set_after_successful_login(): void
    {
        $user = User::factory()->create(['user_role_id' => 1, 'last_login_at' => null]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_first_login_at_is_set_on_first_login_only(): void
    {
        $user = User::factory()->create(['user_role_id' => 1, 'first_login_at' => null]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $user->refresh();
        $firstLoginAt = $user->first_login_at;
        $this->assertNotNull($firstLoginAt);

        $this->post('/logout');

        $this->travel(1)->day();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $user->refresh();

        $this->assertTrue($firstLoginAt->equalTo($user->first_login_at));
    }

    public function test_trial_expired_flash_is_set_when_trial_over_and_no_paid_tariff(): void
    {
        $user = User::factory()->create([
            'user_role_id' => 1,
            'first_login_at' => now()->subDays(4),
            'free_contacts' => 3,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHas('show_trial_expired_modal', true);
    }

    public function test_trial_expired_flash_is_not_set_when_trial_still_active(): void
    {
        $user = User::factory()->create([
            'user_role_id' => 1,
            'first_login_at' => now()->subDay(),
            'free_contacts' => 3,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionMissing('show_trial_expired_modal');
    }

    public function test_trial_expired_flash_is_not_set_when_active_paid_tariff_exists(): void
    {
        $user = User::factory()->create([
            'user_role_id' => 1,
            'first_login_at' => now()->subDays(10),
            'free_contacts' => 0,
        ]);

        $paymentTariff = \App\Models\PaymentTariff::create([
            'api_data_type' => \App\Enum\ApiDataTypeEnum::Builder->value,
            'title' => 'Тестовый тариф',
            'price' => 1000,
            'period' => 30,
            'count_contacts' => 10,
            'status' => \App\Enum\PaymentTariffStatusEnum::Active,
        ]);

        \App\Models\UserTariff::create([
            'user_id' => $user->id,
            'payment_tariff_id' => $paymentTariff->id,
            'api_data_type' => \App\Enum\ApiDataTypeEnum::Builder->value,
            'count_contacts' => 10,
            'count_contacts_left' => 10,
            'status' => \App\Enum\UserTariffStatusEnum::Active,
            'date_start' => now()->subDay(),
            'date_end' => now()->addDays(29),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionMissing('show_trial_expired_modal');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
