<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EnableSpecialistChannelsCommandTest extends TestCase
{
    public function test_requires_dry_run_or_apply(): void
    {
        $code = Artisan::call('app:channels:enable-specialists');

        $this->assertSame(2, $code);
        $this->assertStringContainsString('ровно один режим', Artisan::output());
    }

    public function test_rejects_both_dry_run_and_apply(): void
    {
        $code = Artisan::call('app:channels:enable-specialists', [
            '--dry-run' => true,
            '--apply' => true,
        ]);

        $this->assertSame(2, $code);
    }

    public function test_rejects_invalid_source(): void
    {
        $code = Artisan::call('app:channels:enable-specialists', [
            '--dry-run' => true,
            '--source' => 'facebook',
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('telegram или vk', Artisan::output());
    }
}
