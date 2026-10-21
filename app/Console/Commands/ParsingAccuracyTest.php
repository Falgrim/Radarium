<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ParsingTestRun;
use App\Services\ParsingAccuracyTestService;
use Illuminate\Console\Command;
use Throwable;

class ParsingAccuracyTest extends Command
{
    protected $signature = 'app:parsing-accuracy-test
        {run? : ID уже созданного прогона}
        {--foreground : Создать прогон и выполнить его в этом процессе}';

    protected $description = 'Тест отбора целевых сообщений строителей локальной моделью. Не меняет посты и каталог.';

    public function handle(ParsingAccuracyTestService $service): int
    {
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            if ($this->option('foreground')) {
                $run = $service->openRun();
                $this->info('Прогон '.$run->id);

                return $this->executeRun($service, $run);
            }

            $runId = $this->argument('run');
            if ($runId === null || ! ctype_digit((string) $runId)) {
                $this->error('Укажите ID прогона или флаг --foreground.');

                return 1;
            }

            $run = ParsingTestRun::query()->find((int) $runId);
            if ($run === null) {
                $this->error('Прогон не найден.');

                return 1;
            }

            return $this->executeRun($service, $run);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function executeRun(ParsingAccuracyTestService $service, ParsingTestRun $run): int
    {
        $service->execute($run, function (string $line): void {
            $this->line($line);
        });

        $run->refresh();
        $this->info(sprintf(
            'Статус: %s. Целевых: %d. Ошибок: %d.',
            $run->status->label(),
            $run->target_count,
            $run->error_count
        ));

        return $run->status->value === 'failed' ? 1 : 0;
    }
}
