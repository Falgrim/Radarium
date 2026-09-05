<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiRequeuePostsTest extends TestCase
{
    public function test_rejects_unknown_type(): void
    {
        $code = Artisan::call('app:ai_parse:requeue', ['--type' => 'wrong']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--type', Artisan::output());
    }

    public function test_rejects_unknown_status(): void
    {
        $code = Artisan::call('app:ai_parse:requeue', ['--status' => 'wrong']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--status', Artisan::output());
    }

    public function test_rejects_unknown_date_field(): void
    {
        $code = Artisan::call('app:ai_parse:requeue', ['--date-field' => 'wrong']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--date-field', Artisan::output());
    }

    public function test_rejects_period_without_both_bounds(): void
    {
        $code = Artisan::call('app:ai_parse:requeue', ['--from' => '2026-01-01']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--from', Artisan::output());
    }
}
