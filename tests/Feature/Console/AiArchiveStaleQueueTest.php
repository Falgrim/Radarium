<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiArchiveStaleQueueTest extends TestCase
{
    public function test_rejects_unknown_type(): void
    {
        $code = Artisan::call('app:ai_parse:archive-stale-queue', ['--type' => 'wrong', '--dry-run' => true]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--type', Artisan::output());
    }

    public function test_rejects_non_positive_months(): void
    {
        $code = Artisan::call('app:ai_parse:archive-stale-queue', ['--months' => '0', '--dry-run' => true]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--months', Artisan::output());
    }

    public function test_requires_a_mode(): void
    {
        $code = Artisan::call('app:ai_parse:archive-stale-queue', []);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--dry-run', Artisan::output());
    }

    public function test_rejects_both_modes_at_once(): void
    {
        $code = Artisan::call('app:ai_parse:archive-stale-queue', ['--dry-run' => true, '--apply' => true]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--apply', Artisan::output());
    }
}
