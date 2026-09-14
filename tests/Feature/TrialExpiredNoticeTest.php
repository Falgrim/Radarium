<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialExpiredNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_autoshow_script_present_when_flash_flag_set(): void
    {
        $user = User::factory()->create(['user_role_id' => 1]);

        $response = $this->actingAs($user)
            ->withSession(['show_trial_expired_modal' => true])
            ->get(route('catalog.builders'));

        $response->assertOk();
        $response->assertSee("rdModalShow('rd-trial-expired')", false);
    }

    public function test_modal_autoshow_script_absent_when_flag_not_set(): void
    {
        $user = User::factory()->create(['user_role_id' => 1]);

        $response = $this->actingAs($user)->get(route('catalog.builders'));

        $response->assertOk();
        $response->assertDontSee("rdModalShow('rd-trial-expired')", false);
    }

    public function test_modal_not_shown_again_on_next_request_without_new_login(): void
    {
        $user = User::factory()->create([
            'user_role_id' => 1,
            'first_login_at' => now()->subDays(4),
            'free_contacts' => 3,
        ]);

        // Реальный вход — листенер Login выставляет флеш-флаг.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Первая страница после логина (тот же клиент/сессия) — флеш ещё жив.
        $first = $this->get(route('catalog.builders'));
        $first->assertSee("rdModalShow('rd-trial-expired')", false);

        // Повторный запрос в той же сессии — флеш уже израсходован.
        $second = $this->get(route('catalog.builders'));
        $second->assertDontSee("rdModalShow('rd-trial-expired')", false);
    }

    public function test_modal_markup_is_always_present_but_hidden_by_default(): void
    {
        $user = User::factory()->create(['user_role_id' => 1]);

        $response = $this->actingAs($user)->get(route('catalog.builders'));

        $response->assertSee('id="rd-trial-expired"', false);
        $response->assertSee('id="rd-trial-expired-tariff-btn"', false);
        $response->assertSee('id="rd-trial-expired-profile-btn"', false);
        $response->assertSee('#rd-trial-expired-tariff-btn', false);
        $response->assertSee('#rd-trial-expired-profile-btn', false);
    }
}
