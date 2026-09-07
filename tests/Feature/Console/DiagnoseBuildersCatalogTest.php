<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DiagnoseBuildersCatalogTest extends TestCase
{
    public function test_rejects_malformed_since_date(): void
    {
        $code = Artisan::call('app:catalog:diagnose-builders', ['--since' => '01.05.2026']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--since', Artisan::output());
    }

    public function test_rejects_impossible_since_date(): void
    {
        $code = Artisan::call('app:catalog:diagnose-builders', ['--since' => '2026-13-45']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--since', Artisan::output());
    }

    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('app:catalog:diagnose-builders', Artisan::all());
    }
}
