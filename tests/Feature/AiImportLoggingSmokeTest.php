<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Log\LogManager;
use Tests\TestCase;

/**
 * Проверка, что каналы логов для AI-импорта зарегистрированы и реально пишутся на диск.
 */
final class AiImportLoggingSmokeTest extends TestCase
{
    public function test_builder_type_override_and_catalog_gate_channels_write_to_storage(): void
    {
        $channels = config('logging.channels');
        $this->assertArrayHasKey('builder_type_override', $channels);
        $this->assertArrayHasKey('catalog_publication_gate', $channels);
        $this->assertSame('daily', $channels['builder_type_override']['driver']);
        $this->assertSame('daily', $channels['catalog_publication_gate']['driver']);

        /** @var LogManager $log */
        $log = $this->app->make('log');

        $token = 'phpunit_smoke_'.uniqid('', true);

        $log->channel('builder_type_override')->info($token, ['channel' => 'builder_type_override']);
        $log->channel('catalog_publication_gate')->info($token, ['channel' => 'catalog_publication_gate']);

        $date = now()->format('Y-m-d');
        foreach (['builder_type_override', 'catalog_publication_gate'] as $name) {
            $path = storage_path("logs/{$name}-{$date}.log");
            $this->assertFileExists($path, "Ожидался файл лога: {$path}");
            $this->assertStringContainsString(
                $token,
                (string) file_get_contents($path),
                "В {$path} должна быть тестовая запись"
            );
        }
    }
}
