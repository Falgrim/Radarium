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
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist')->sendOutputTo(base_path('tg_parse.log'));
                        $schedule->command('app:tg_parse:builder')->sendOutputTo(base_path('tg_parse.log'));
                        $schedule->command('app:tg_parse:company')->sendOutputTo(base_path('tg_parse.log'));
                });
            } elseif ($readSourceCron->value <= 1439) {
                // Каждые Х часов
                $schedule->cron('0 */'.ceil($readSourceCron->value/60).' * * *')
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist')->sendOutputTo(base_path('tg_parse.log'));
                        $schedule->command('app:tg_parse:builder')->sendOutputTo(base_path('tg_parse.log'));
                        $schedule->command('app:tg_parse:company')->sendOutputTo(base_path('tg_parse.log'));
                    });
            } else {
                // Каждый день в 00:00
                $schedule->cron('0 0 * * *')
                    ->group(function (Schedule $schedule) {
                        $schedule->command('app:tg_parse:specialist')->sendOutputTo(base_path('tg_parse.log'));
                        $schedule->command('app:tg_parse:builder')->sendOutputTo(base_path('tg_parse.log'));
                        $schedule->command('app:tg_parse:company')->sendOutputTo(base_path('tg_parse.log'));
                    });
            }
        } else {
            $schedule->command('app:tg_parse:specialist')->hourly()->sendOutputTo(base_path('tg_parse.log'));;
            $schedule->command('app:tg_parse:builder')->hourly()->sendOutputTo(base_path('tg_parse.log'));;
            $schedule->command('app:tg_parse:company')->hourly()->sendOutputTo(base_path('tg_parse.log'));;
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

        $schedule->command('app:tariff:users')->everyFifteenMinutes();

        // Проверка статуса новых платежей
        $schedule->command('app:payments:check')->everyTwoMinutes();

        // Удаление просроченных токенов восстановления паролей
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();
    })->create();
