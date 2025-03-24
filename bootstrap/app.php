<?php

use App\Infrastructures\Facades\Repositories;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )->withMiddleware(function (Middleware $middleware) {
        //
    })->withExceptions(function (Exceptions $exceptions) {
        //
    })->withSchedule(function (Schedule $schedule) {

        $readSourceCron = Repositories::setting()->findByName('read_source_cron');

        // Поиск новых постов из источников
        if ($readSourceCron?->value) {
            if ($readSourceCron->value <= 59) {
                // Каждые X минут
                $schedule->cron('*/'.$readSourceCron->value.' * * * *')
                    ->withoutOverlapping()
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist');
                        $schedule->command('app:tg_parse:builder');
                        $schedule->command('app:tg_parse:company');
                });
            } elseif ($readSourceCron->value <= 1439) {
                // Каждые Х часов
                $schedule->cron('0 */'.ceil($readSourceCron->value/60).' * * *')
                    ->withoutOverlapping()
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist');
                        $schedule->command('app:tg_parse:builder');
                        $schedule->command('app:tg_parse:company');
                    });
            } else {
                // Каждый день в 00:00
                $schedule->cron('0 0 * * *')
                    ->withoutOverlapping()
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist');
                        $schedule->command('app:tg_parse:builder');
                        $schedule->command('app:tg_parse:company');
                    });
            }
        } else {
            $schedule->command('app:tg_parse:specialist')->hourly()->withoutOverlapping();
            $schedule->command('app:tg_parse:builder')->hourly()->withoutOverlapping();
            $schedule->command('app:tg_parse:company')->hourly()->withoutOverlapping();
        }

        // Отправка запросов в ИИ
        $schedule->everyThirtyMinutes()
            ->runInBackground()
            ->withoutOverlapping()
            ->group(function (Schedule $schedule) {
                $schedule->command('app:ai_parse:specialist');
                $schedule->command('app:ai_parse:builder');
                $schedule->command('app:ai_parse:company');
            });

        // Синхронизация с Тубус
        $schedule->command('app:tubus')->hourly()->withoutOverlapping();

        // Удаление просроченных токенов восстановления паролей
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();
    })->create();
