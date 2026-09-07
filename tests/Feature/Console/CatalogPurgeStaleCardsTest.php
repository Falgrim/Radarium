<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CatalogPurgeStaleCardsTest extends TestCase
{
    public function test_rejects_unknown_type(): void
    {
        $code = Artisan::call('app:catalog:purge-stale-cards', [
            '--type' => 'company',
            '--before' => '2026-03-01',
            '--dry-run' => true,
        ]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--type', Artisan::output());
    }

    public function test_requires_before_date(): void
    {
        $code = Artisan::call('app:catalog:purge-stale-cards', ['--dry-run' => true]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--before', Artisan::output());
    }

    public function test_rejects_malformed_before_date(): void
    {
        $code = Artisan::call('app:catalog:purge-stale-cards', [
            '--before' => '01.03.2026',
            '--dry-run' => true,
        ]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--before', Artisan::output());
    }

    public function test_rejects_impossible_before_date(): void
    {
        $code = Artisan::call('app:catalog:purge-stale-cards', [
            '--before' => '2026-13-45',
            '--dry-run' => true,
        ]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--before', Artisan::output());
    }

    public function test_requires_a_mode(): void
    {
        $code = Artisan::call('app:catalog:purge-stale-cards', ['--before' => '2026-03-01']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--dry-run', Artisan::output());
    }

    public function test_rejects_both_modes_at_once(): void
    {
        $code = Artisan::call('app:catalog:purge-stale-cards', [
            '--before' => '2026-03-01',
            '--dry-run' => true,
            '--apply' => true,
        ]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--apply', Artisan::output());
    }
}
