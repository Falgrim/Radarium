<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'user_role_id' => 1,
            'free_contacts' => 3,
        ], $attributes));
    }

    public function test_trial_is_active_when_first_login_is_null(): void
    {
        $user = $this->makeUser(['first_login_at' => null]);

        $this->assertTrue($user->isTrialActive());
        $this->assertFalse($user->isTrialExpired());
        $this->assertNull($user->getTrialEndsAt());
    }

    public function test_trial_is_active_within_three_days(): void
    {
        $user = $this->makeUser(['first_login_at' => Carbon::now()->subDays(2)]);

        $this->assertTrue($user->isTrialActive());
        $this->assertFalse($user->isTrialExpired());
    }

    public function test_trial_is_expired_after_three_days(): void
    {
        $user = $this->makeUser(['first_login_at' => Carbon::now()->subDays(4)]);

        $this->assertFalse($user->isTrialActive());
        $this->assertTrue($user->isTrialExpired());
    }

    public function test_get_left_contacts_reports_zero_after_trial_expired_even_with_free_contacts(): void
    {
        $user = $this->makeUser([
            'first_login_at' => Carbon::now()->subDays(10),
            'free_contacts' => 3,
        ]);

        $leftContacts = $user->getLeftContacts();

        $this->assertSame(0, $leftContacts['count_contacts_left']);
        $this->assertSame(0, $leftContacts['days_left']);
    }

    public function test_get_left_contacts_days_left_reflects_trial_window(): void
    {
        $user = $this->makeUser([
            'first_login_at' => Carbon::now()->startOfDay(),
            'free_contacts' => 3,
        ]);

        $leftContacts = $user->getLeftContacts();

        $this->assertSame(3, $leftContacts['count_contacts_left']);
        $this->assertSame(User::TRIAL_DAYS, $leftContacts['days_left']);
    }
}
