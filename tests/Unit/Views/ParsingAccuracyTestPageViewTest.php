<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use App\Enum\ParsingTestRunStatus;
use App\Models\ParsingTestRun;
use Tests\TestCase;

final class ParsingAccuracyTestPageViewTest extends TestCase
{
    public function createApplication()
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        return parent::createApplication();
    }

    public function test_stopping_state_shows_only_the_stopping_control(): void
    {
        $html = $this->render(isActive: true, isStopping: true);

        $this->assertStringContainsString('Останавливается', $html);
        $this->assertStringNotContainsString('Тест выполняется', $html);
        $this->assertStringNotContainsString('Прервать тест', $html);
        $this->assertStringNotContainsString('Запустить тест', $html);
        $this->assertSame(1, substr_count($html, '<button'));
    }

    public function test_running_state_offers_only_cancel(): void
    {
        $html = $this->render(isActive: true, isStopping: false);

        $this->assertStringContainsString('Прервать тест', $html);
        $this->assertStringContainsString('Выполняется', $html);
        $this->assertStringNotContainsString('Тест выполняется', $html);
        $this->assertStringNotContainsString('Останавливается', $html);
        $this->assertStringNotContainsString('Запустить тест', $html);
        $this->assertSame(1, substr_count($html, '<button'));
    }

    public function test_idle_state_offers_only_start(): void
    {
        $html = $this->render(isActive: false, isStopping: false, withRun: false);

        $this->assertStringContainsString('Запустить тест', $html);
        $this->assertStringNotContainsString('Прервать тест', $html);
        $this->assertStringNotContainsString('Останавливается', $html);
        $this->assertSame(1, substr_count($html, '<button'));
    }

    private function render(bool $isActive, bool $isStopping, bool $withRun = true): string
    {
        $run = null;
        if ($withRun) {
            $run = new ParsingTestRun([
                'status' => ParsingTestRunStatus::Running,
                'sample_size' => 300,
                'sampled_count' => 20,
                'processed_count' => 8,
                'target_count' => 2,
                'heuristic_reject_count' => 1,
                'error_count' => 0,
                'extraction_error_count' => 0,
                'model' => 'qwen3.5:9b',
                'prompt_label' => 'Промпт источников строителей',
            ]);
            if ($isStopping) {
                $run->cancel_requested_at = now();
            }
        }

        return view('moonshine.parsing-accuracy-test', [
            'run' => $run,
            'messages' => null,
            'isActive' => $isActive,
            'isStopping' => $isStopping,
            'error' => null,
            'startUrl' => '/admin/parsing-accuracy-test/start',
            'cancelUrl' => '/admin/parsing-accuracy-test/cancel',
            'postFormUrl' => static fn (int $postId): string => '/admin/post/'.$postId,
            'authorDetailUrl' => static fn (int $authorId): string => '/admin/author/'.$authorId,
        ])->render();
    }
}
